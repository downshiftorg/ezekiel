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
			['with' => '*', 'return' => 'somethjing else']
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


	function testCanInspectInvocationsWithGetInvocations() {
		$stub = $this->stub('SomeClass', ['foo' => 'some value']);
		$stub->foo();
		$stub->foo('again');
		$invocations = $this->getInvocations($stub, 'foo');

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






