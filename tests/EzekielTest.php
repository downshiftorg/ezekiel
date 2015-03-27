<?php
namespace NetRivet\Ezekiel;


abstract class TestCase extends \PHPUnit_Framework_TestCase {

	use Ezekiel;

	protected function transformClass($in) {
		return '\NetRivet\Ezekiel\\' . $in;
	}
}


class SomeClass {
	public function foo() {}
	public function bar() {}
}




class EzekielTestCase extends TestCase {


	function testArgWildcardMatchesNull() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['with' => ['*', 'jimjam', 'qux'], 'returns' => 'result'],
		]]);

		$this->assertSame('result', $stub->foo(null, 'jimjam', 'qux'));
	}


	function testCacheNotMixedUpByPropSyntax() {
		$this->myProp = 'foo';
		$stub1 = $this->stub('SomeClass', ['foo' => '@myProp']);

		$this->myProp = 'bar';
		$stub2 = $this->stub('SomeClass', ['foo' => '@myProp']);

		$this->assertNotSame($stub1, $stub2);
	}


	function testCacheNotMixedUpByPropSyntaxWhenUsingLongSyntax() {
		$this->myProp = 'foo';
		$stub1 = $this->stub('SomeClass', ['foo' => [['with' => '*', 'returns' => '@myProp'],]]);

		$this->myProp = 'bar';
		$stub2 = $this->stub('SomeClass', ['foo' => [['with' => '*', 'returns' => '@myProp'],]]);

		$this->assertNotSame($stub1, $stub2);
	}


	function testReturnClosure() {
		$stub = $this->stub('SomeClass', ['foo' => function(){return 'closure';}]);
		$this->assertSame('closure', $stub->foo());
	}


	function testReturnedClosureIsBoundToTestCase() {
		$stub = $this->stub('SomeClass', ['bar' => function(){return $this->someProp;}]);
		$this->someProp = 'test bound';
		$this->assertSame('test bound', $stub->bar());
	}


	function testClosureReturnGetsPassedArgs() {
		$stub = $this->stub('SomeClass', ['foo' => function($arg1){return strtoupper($arg1);}]);
		$this->assertSame('BAR', $stub->foo('bar'));
	}


	function testCanReturnLiveTestCasePropertiesWithAtSyntax() {
		$stub = $this->stub('SomeClass', ['foo' => '@prop']);

		$this->prop = 'bar';
		$this->assertSame('bar', $stub->foo());

		$this->prop = 'baz';
		$this->assertSame('baz', $stub->foo());
	}


	function testCanReturnLiveTestCasePropertiesWithAtSyntaxAndLongSyntax() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['with' => ['somearg'], 'returns' => '@herp'],
		]]);

		$this->herp = 'derp';
		$this->assertSame('derp', $stub->foo('somearg'));

		$this->herp = 'qux';
		$this->assertSame('qux', $stub->foo('somearg'));
	}


	function testCallsAcceptsAliasToLastInvocation() {
		$stub = $this->stub('SomeClass', ['foo' => 'test last alias']);
		$stub->foo(1);
		$stub->foo(2);

		$this->assertSame(2, $this->calls($stub, 'foo', '~last', 0));
	}


	function testCanMatchInvocationWithWildcardAllArgs() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['expectArgs' => '*', 'times' => 1, 'andReturn' => 'some val 123'],
		]]);

		$this->assertSame('some val 123', $stub->foo());
		$this->verifyMockObjects();
		$this->assertSame(1, $this->getNumAssertions());
	}


	function testCanWildCardIndividualArgumentsForStubs() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['with' => ['foo', '*'], 'returns' => 'something'],
		]]);

		$this->assertSame('something', $stub->foo('foo', 'jimjam'));
	}


	function testCanReturnArbitraryArgsFromLongSyntax() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['with' => ['*'], 'returns' => '~arg=2'],
		]]);

		$this->assertSame('jimjam', $stub->foo('herpderp', 'jimjam'));
	}


	function testCanReturnArbitraryArgsFromLongSyntaxThroughCallable() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['with' => ['*'], 'returns' => '~arg=2 -> strtoupper'],
		]]);

		$this->assertSame('JIMJAM', $stub->foo('herpderp', 'jimjam'));
	}


	function testCanReturnArbitraryArgsUsingShortSyntax() {
		$stub = $this->stub('SomeClass', ['bar' => '~arg=3']);
		$this->assertSame(3, $stub->bar(1, 2, 3));
	}


	function testReturnsFirstArgNoMatterHowManyArgsReceivedIfUsingShortSyntax() {
		$stub = $this->stub('SomeClass', ['bar' => '~firstArg']);
		$this->assertSame('hello', $stub->bar('hello', 'jim', 'jam'));
	}


	function testCanUseShortSyntaxToIndicateMethodThatShouldNeverBeCalled() {
		$stub = $this->stub('SomeClass', ['foo' => '~neverCalled']);

		$this->verifyMockObjects();
		$this->assertSame(1, $this->getNumAssertions());
	}


	function testReturnValue() {
		$stub = $this->stub('SomeClass', ['foo' => 'bar']);

		$this->assertSame('bar', $stub->foo());
	}


	function testReturnJoinedArgs() {
		$stub = $this->stub('SomeClass', ['foo' => '~joinArgs|*', 'bar' => '~joinArgs']);

		$this->assertSame('foo*bar', $stub->foo('foo', 'bar'));
		$this->assertSame('foobar',  $stub->bar('foo', 'bar'));
	}


	function testReturnsFirstArgIfSpecialStringPassed() {
		$stub = $this->stub('SomeClass', ['foo' => '~firstArg']);

		$this->assertSame('baz', $stub->foo('baz'));
	}


	function testReturnsSelfIfSpecialStringPassed() {
		$stub = $this->stub('SomeClass', ['foo' => '~self']);

		$this->assertSame($stub, $stub->foo());
	}


	function testShortSyntaxReturningAssociativeArrayNotConfusedWithLongSyntax() {
		$stub = $this->stub('SomeClass', ['foo' => ['name' => 'CustomFontName', 'slug' => 'customfontslug']]);

		$this->assertSame(['name' => 'CustomFontName', 'slug' => 'customfontslug'], $stub->foo());
	}


	function testMatchesAllArgsIfWithIsArray() {
		$stub = $this->stub('SomeClass', [
			'foo' => [
				['with' => ['foo', 'bar'], 'returns' => 'baz'],
				['with' => '*',            'returns' => 'foo'],
			],
		]);

		$this->assertSame('baz', $stub->foo('foo', 'bar'));
		$this->assertSame('foo', $stub->foo('foo'));
	}


	function testFirstArgCanBePipedThroughCallableFunc() {
		$stub = $this->stub('SomeClass', ['bar' => '~firstArg -> htmlspecialchars']);
		$this->assertSame(htmlspecialchars('"baz"'), $stub->bar('"baz"'));
	}


	function testReturnDifferentResultsForDifferentInvocations() {
		$stub = $this->stub('SomeClass', [
			'foo' => [
				['with' => 'bar', 'returns' => 'baz'],
				['with' => 'jim', 'returns' => 'jam'],
				['with' => '1st', 'returns' => '~firstArg'],
				['with' => 'me',  'returns' => '~self'],
				['with' => '*',   'returns' => 'foo'],
			],
		]);

		$this->assertSame('baz', $stub->foo('bar'));
		$this->assertSame('jam', $stub->foo('jim'));
		$this->assertSame('foo', $stub->foo('wup'));
		$this->assertSame('1st', $stub->foo('1st'));
		$this->assertSame($stub, $stub->foo('me'));
	}


	function testStubWithExpectationsMustBeMet() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['expectArgs' => ['arg']],
		]]);
		$stub->foo('arg');
		$this->verifyMockObjects();
		$this->assertSame(1, $this->getNumAssertions());
	}


	function testStubWithGenericExpectationsWorks() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['expectArgs' => ['jimjam'], 'andReturn' => 'hashbaz'],
			['with' => '*', 'return' => 'somethjing else'],
		]]);

		$this->assertSame('hashbaz', $stub->foo('jimjam'));

		$this->verifyMockObjects();
		$this->assertSame(1, $this->getNumAssertions());
	}


	function testStubWithMultipleArgsIncludingWildCard() {
		$stub = $this->stub('SomeClass', ['foo' => [
			['expectArgs' => ['1', '2', '*']],
		]]);
		$stub->foo('1', '2', 'foobar');
		$this->verifyMockObjects();
		$this->assertSame(1, $this->getNumAssertions());
	}


	function testMockCanSayHowManyInvocations() {
		$mock = $this->stub('SomeClass', ['foo' => [
			['expectArgs' => ['bar'], 'times' => 2],
		]]);
		$mock->foo('bar');
		$mock->foo('bar');
		$this->verifyMockObjects();
		$this->assertSame(1, $this->getNumAssertions());
	}


	function testCanUseShortReturnSyntaxToReturnIndexedArrayOfObjects() {
		$objs = [(object) [], (object) []];
		$mock = $this->stub('SomeClass', ['foo' => $objs]);

		$this->assertSame($objs, $mock->foo());
	}


	function testCanInspectInvocationsWithcalls() {
		$stub = $this->stub('SomeClass', ['foo' => 'some value']);
		$stub->foo();
		$stub->foo('again');
		$invocations = $this->calls($stub, 'foo');

		$this->assertCount(2, $invocations);
		$this->assertSame([], $invocations[0]);
		$this->assertSame('again', $invocations[1][0]);
	}


	function testReturnsCachedObjectIfExactSameStubRequested() {
		$stub1 = $this->stub('SomeClass', ['foo' => 'blah blah']);
		$stub2 = $this->stub('SomeClass', ['foo' => 'blah blah']);

		$this->assertSame($stub1, $stub2);
	}


	function testDoesNotIncorrectlyCacheStubsReturningStubs() {
		$stub1 = $this->stub('SomeClass', ['foo' => __FUNCTION__ . '1']);
		$stub2 = $this->stub('SomeClass', ['foo' => __FUNCTION__ . '2']);
		$stub3 = $this->stub('SomeClass', ['foo' => $stub1]);
		$stub4 = $this->stub('SomeClass', ['foo' => $stub2]);

		$this->assertNotSame($stub3->foo(), $stub4->foo());
	}
}






