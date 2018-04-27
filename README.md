Ezekiel
==

Ezekiel gives you an alternate wrapper, some syntactic sugar, and added convenience methods for working with the <a href="https://github.com/phpspec/prophecy">phpspec/prophecy</a> library from within <a href="http://phpunit.de">PHPUnit</a>.

For making simple stubs and mocks with Prophecy, Ezekiel reduces a lot of the boilerplate code and gives you a terse, but fairly expressive syntax for creating test double objects.

## A simple example

Say you wanted to a quick and dirty test dummy. Using Prophecy directly, you'd need to do something like this:

```php
<?php

$prophecy = $this->prophesize('SomeClass');
$prophecy->someMethod()->willReturn(false);

$stub = $prophecy->reveal();
```

With Ezekiel, you would just write this:

```php
<?php

$stub = $this->stub('SomeClass', ['someMethod' => false]);
```

## Short syntax

For really simple stub objects, when you don't care about argument matching or returning different values on different invocations, you can use Ezekiel's short syntax:

```php
<?php

// stub a single method
$foo = $this->stub('Foo', ['someMethod' => false]);

// stub multiple methods
$bar = $this->stub('Bar', [
	'someMethod'  => false,
	'otherMethod' => true,
	'jimJam'      => 'some text'
]);
```

## Special return values

Stubs/mocks can **return the first argument** passed by using the special string `~firstArg`

```php
<?php

$stub = $this->stub('Foo', ['bar' => '~firstArg']);
$stub->bar('baz'); // returns 'baz'
```

Stubs/mocks can **return the arbitrary arguments** passed by using the special string `~arg=X`

```php
<?php

$stub = $this->stub('Foo', ['bar' => '~arg=3']);
$stub->bar('baz', 'foo', 'herp'); // returns 'herp'
```

Stubs/mocks can **return themselves** by using the special string `~self`

```php
<?php

$stub = $this->stub('Foo', ['bar' => '~self']);
$stub->bar(); // returns $stub
```

Stub/mocks can also **return references to test-case public properties** using the coffee script-ish `@` syntax. This is really useful if you want to setup a generic stub or mock in a test case `setUp()` method and modify individual components of what the test double returns for different test methods.

```php
<?php

$stub = $this->stub('Foo', ['bar' => '@myProp']);

$this->myProp = true;
$stub->bar(); // returns true

$this->myProp = false;
$stub->bar(); // returns false
```

Stub/mocks can also **pass arguments through callable function names** using the skinny arrow ` -> `

```php
<?php

$stub = $this->stub('Foo', ['bar' => '~firstArg -> strtoupper']);
$stub->bar('baz'); // returns 'BAZ'

$stub = $this->stub('Foo', ['bar' => '~arg=2 -> strtoupper']);
$stub->bar('foo', 'bar'); // returns 'BAR'
```

Stub/mocks can also **return a joined version of all arguments** passed by using the special string `~joinArgs` or `~joinArgs|DELIMETER`. This can be useful in very simple situations for verifying method invocations when it's not worth it to use the longer syntax or `$this->calls()`

```php
<?php

$stub = $this->stub('Foo', ['bar' => '~joinArgs|*']);
$stub->bar('baz', 'herp', 'derp'); // returns 'baz*herp*derp'
```

Stubs/mocks can also **return the result of invoking an anonymous function** which is bound to the test-case's `$this` and receives the invocation arguments as arguments.

```php
<?php

$stub = $this->stub('Foo', ['bar' => function($str) {
	return $this->someProp . ' ' . strtoupper($str) . '!';
}]);

$this->someProp = 'Hello';
$stub->bar('world'); // returns 'Hello WORLD!'

$this->someProp = 'Howdy';
$stub->bar('friends'); // returns 'Howdy FRIENDS!'
```

You can also **combine the `@prop` syntax with anonymous functions** to dynamically return the result of invoking a public property which is a anonymous function:

```php
<?php

$stub = $this->stub('Foo', ['bar' => '@someProp']);

$this->someProp = function($arg1) {
	return $arg1 === 'foo' ? 'bar' : 'baz';
};

$stub->bar('foo'); // returns 'bar'
$stub->bar('qux'); // returns 'baz'
```

## Longer syntax

Ezekiel can also accept a longer syntax that allows you to match arguments and return different values using `'with'` and `'return'` keywords.

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['with' => ['jim'],  'return' => 'jam'],
	['with' => ['herp'], 'return' => 'derp'],
]]);

$stub->bar('jim');  // returns 'jam'
$stub->bar('herp'); // returns 'derp'
```

Normally, the `'with'` property must be an array, and correponds to the arguments that are being matched. If you don't care to match a certain argument, pass in the string `*` as a wildcard:

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['with' => ['herp', 'derp'], 'return' => 'slurp'],
	['with' => ['herp', '*'],    'return' => false],
]]);

$stub->bar('herp', 'derp');  // returns 'slurp'
$stub->bar('herp', 'foo');   // returns false
```

You can also pass in the non-array string value `'*'` to match any combination of arguments.  This is useful for specifying default return values.

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['with' => ['herp'], 'return' => 'derp'],
	['with' => '*',      'return' => 'my default value'],
]]);

$stub->bar('herp');  // returns 'derp'
$stub->bar('foo');   // returns 'my default value'
$stub->bar('bar');   // returns 'my default value'
```

## Creating mocks

By default, Ezekiel creates test stubs, not mocks.  That means that we're specifying the behavior of the test double objects within the system under tests, but not directly testing how these objects are used.  If we want to **create true mock objects that contain expections**, Ezekiel can help with that too. To create a mock object that contains expectation, just use the longer syntax explained above, but use `'expect'` in place of `'with'`.

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['expect' => ['jim'],  'return' => 'jam'],
]]);

// returns 'jam' and adds to assertion count
// if not invoked with expected args, produces PHPUnit failure
$stub->bar('jim');
```

You can also specify **how many times a mock invocation is expected** using the `'times'` keyword.  If you do not set the `'times'` property, Ezekiel will treat it as though you expected an invocation of that method with those arguments at least once.

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['expect' => ['jim'],  'return' => 'jam', 'times' => 1],
]]);

// causes PHPUnit failure because invoked too many times
$stub->bar('jim');
$stub->bar('jim');
```

Ezekiel also allows you to do **simple argument wildcarding** by using the special string `'*'`.

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['expect' => ['jim', '*'],  'return' => 'jam', 'times' => 1],
]]);

// causes PHPUnit pass because we wild-carded the second arg
$stub->bar('jim', 'jam');
```

You can make **multiple expectations for a single method**:

```php
<?php

$stub = $this->stub('Foo', ['bar' => [
	['expect' => ['foobar'],   'return' => 'hashbaz'],
	['expect' => ['jim', '*'], 'return' => 'jam', 'times' => 1],
	['expect' => ['herp', 1],  'return' => [1, 2, 3]],
]]);
```

If you want to **expect that a method should never be called**, you can use a form of the short-syntax and the special string `~neverCalled` to indicate that:

```php
<?php

$stub = $this->stub('Foo', ['bar' => '~neverCalled']);

// causes PHPUnit failure
$stub->bar();
```

## Inspecting invocations

Prophecy keeps a record of your test double method invocations, so you can do cool stuff like:

```php
<?php

$prophecy->someMethod()->shouldHaveBeenCalled();
```

Ezekiel gives you an alternative method for inspecting recorded invocations that can be helpful in making very specific test spy assertions without writing crazy prediction matchers and callbacks.

```php
<?php

$stub = $this->stub('SomeClass', ['someMethod' => false]);
$stub->someMethod('foo', 'bar');

// returns array of all recorded invocations => [['foo', 'bar']];
$this->calls($stub, 'someMethod');

// returns arguments for first method invocation => ['foo', 'bar'];
$this->calls($stub, 'someMethod', $invocationIndex = 0);

// returns first argument for first method invocation => 'foo';
$this->calls($stub, 'someMethod', $invocationIndex = 0, $argumentIndex = 0);
```

You can also pass the special string `~last` for the third paramater `$invocationIndex` as a shortcut to returning the last invocation of the requested method. This is useful if you're re-using a cached stub and want to inspect the most recent invocation of a method.

```php
<?php

$stub = $this->stub('SomeClass', ['someMethod' => false]);
$stub->someMethod(1);
$stub->someMethod(false);
$stub->someMethod('foo');

$this->calls($stub, 'someMethod', '~last');    // returns ['foo'];
$this->calls($stub, 'someMethod', '~last', 0); // returns 'foo';
```

**Note:** to use `Ezekiel::calls()` you need to reset the recorded invocations in your teardown method like this: `$this->recordedCalls = [];` The Ezekiel trait provides a `tearDown` method that does this for you, but if you define your own in your test class, you'll need to do it yourself.

##Transforming class names

If you want to make shortcuts to namespaced class names or otherwise transform the class names used by Ezekiel, just overwrite the `transformClass` method of the Ezekiel trait.

```php
<?php

protected transformClass($class) {
	return 'Acme\Foo\Bar\' . $class;
}
```

## Caching

Ezekiel also caches stubbed objects and will returned a cached value if you request a stub with the exact same specifications.  This can give you a noticable speed increase since prophecy doesn't have to dynamically recreate the stubbed class multiple times. It also allows you to not worry about performance when setting up identical stubbed objects in different test methods.

```php
<?php

$stub1 = $this->stub('SomeClass', ['foo' => 'blah blah']);
$stub2 = $this->stub('SomeClass', ['foo' => 'blah blah']);

$this->assertSame($stub1, $stub2); // true
```

## Installation

Pull the package in through Composer.

```js
{
    "require": {
        "downshiftorg/ezekiel": "0.2.*"
    }
}
```

Then, just use the Ezekiel trait in a TestCase class that extends `PHPUnit_Framework_TestCase`.

```php
<?php

namespace Acme\Foo;

class FooTest extends \PHPUnit_Framework_Testcase {

	use \DownShift\Ezekiel\Ezekiel;

}
```

If you want to have Ezekiel automatically available in all of your test cases, add the trait to a child class of `PHPUnit_Framework_TestCase` that all of your test case classes extend, like so:

```php
<?php

namespace Acme;

class AcmeTestCase extends \PHPUnit_Framework_Testcase {

	use \DownShift\Ezekiel\Ezekiel;

}
```
