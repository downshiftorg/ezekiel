<?php


namespace NetRivet\Ezekiel;

use \Prophecy\Argument as Arg;



trait Ezekiel {

	public static $__cachedStubs = [];
	protected $mockExpectations = [];


	public function stub($class, $returns = []) {
		$hash = $this->getArgHash($class, $returns);

		if (isset(self::$__cachedStubs[$hash])) {
			return self::$__cachedStubs[$hash];
		}

		$class = $this->transformClass($class);

		$prophecy = $this->prophesize($class);

		foreach ($returns as $method => $methodReturns) {

			if (is_string($methodReturns) && strpos($methodReturns, '~firstArg') !== false) {

				if (strpos($methodReturns, ' -> ') !== false) {
					$prophecy->{$method}(Arg::any())->will(function ($args) use ($methodReturns) {
						$parts = explode(' -> ', $methodReturns);
						return call_user_func_array(end($parts), $args);
					});

				} else {
					$prophecy->{$method}(Arg::any())->willReturnArgument(0);
				}

			} else if ($methodReturns === '~self') {
				$prophecy->{$method}(Arg::any())->willReturn($prophecy);

			} else {

				if ($this->isShortReturnSyntax($methodReturns)) {
					$methodReturns = [['with' => '*', 'returns' => $methodReturns]];
				}

				foreach ($methodReturns as $index => $return) {

					if (isset($return['expectArgs'])) {
						$methodReturns[$index]['with'] = $return['expectArgs'];
						$expectedArgs = [];

						if ($return['expectArgs'] === '*') {
							$expectedArgs[] = Arg::cetera();

						} else {
							foreach ((array) $return['expectArgs'] as $expected) {
								$expectedArgs[] = $expected === '*' ? '*' : $expected;
							}
						}

						$mockClass = get_class($prophecy->reveal());

						if (!isset($this->mockExpectations[$mockClass])) {
							$this->mockExpectations[$mockClass] = ['prophecy' => $prophecy, 'expectedInvocations' => []];
						}

						if (!isset($this->mockExpectations[$mockClass]['expectedInvocations'][$method])) {
							$this->mockExpectations[$mockClass]['expectedInvocations'][$method] = [];
						}

						$this->mockExpectations[$mockClass]['expectedInvocations'][$method][] = [
							'arguments' => $expectedArgs,
							'times'     => isset($return['times']) ? $return['times'] : '*',
						];
					}
				}

				$prophecy->{$method}(Arg::cetera())->will(function ($args) use ($methodReturns, $prophecy, $method) {

					foreach ($methodReturns as $return) {

						if (isset($return['andReturn'])) {
							$return['returns'] = $return['andReturn'];
						}

						if (!isset($return['returns'])) {
							$return['returns'] = null;
						}

						$return['with'] = (array) $return['with'];

						if ($args === $return['with'] || $return['with'] === ['*']) {

							if ($return['returns'] === '~firstArg') {
								return $args[0];

							} else if ($return['returns'] === '~self') {
								return $prophecy;

							} else if (is_string($return['returns']) && strpos($return['returns'], '~joinArgs') === 0) {
								if (strpos($return['returns'], '|') !== false) {
									$delimiter = str_replace('~joinArgs|', '', $return['returns']);
								} else {
									$delimiter = '';
								}

								return join($delimiter, $args);

							} else {
								return $return['returns'];
							}
						}
					}
				});
			}
		}

		$stub = $prophecy->reveal();
		$stub->__prophecyOrigClass = $class;
		self::$__cachedStubs[$hash] = $stub;

		return $stub;
	}


	public function verifyMockObjects() {

		foreach ((array) $this->mockExpectations as $className => $mock) {

			foreach ($mock['expectedInvocations'] as $method => $expectedInvocations) {

				$actualInvocations = $mock['prophecy']->findProphecyMethodCalls($method, new Arg\ArgumentsWildcard([Arg::cetera()]));

				foreach ($expectedInvocations as $expectedInvocation) {
					$matchedInvocations   = 0;
					$actualInvocationArgs = [];

					foreach ($actualInvocations as $actualInvocation) {

						$actualArguments        = $actualInvocation->getArguments();
						$actualInvocationArgs[] = $actualArguments;

						if ($this->argumentsMatch($actualInvocation->getArguments(), $expectedInvocation['arguments'])) {
							$matchedInvocations++;
						}
					}

					$objMethod = $mock['prophecy']->__prophecyOrigClass . '::' . $method . '()';

					if ($matchedInvocations > 0) {
						if ($expectedInvocation['times'] === '*' || $expectedInvocation['times'] === $matchedInvocations) {
							$this->addToAssertionCount(1);
						} else {
							throw new \PHPUnit_Framework_ExpectationFailedException(sprintf(
								'%s expected %s time%s but called %s time%s',
								$objMethod,
								$expectedInvocation['times'],
								$expectedInvocation['times'] > 1 ? 's' : '',
								$matchedInvocations,
								$matchedInvocations > 1 ? 's' : ''
							));
						}

					} else {

						if ($actualInvocationArgs) {
							$recordedInvocations = [];
							foreach ($actualInvocationArgs as $args) {
								$recordedInvocations[] = $this->getArgDump($args);
							}
							$withExpectedArgs = ' with the expected arguments';
							$actualMsg = "\nActual invocations were:" . join('', $recordedInvocations);

						} else {
							$withExpectedArgs = '. Expected arguments were';
							$actualMsg = "";
						}

						$argDump = $this->getArgDump($expectedInvocation['arguments']);
						throw new \PHPUnit_Framework_ExpectationFailedException("$objMethod was not invoked{$withExpectedArgs}:" . $argDump . $actualMsg);
					}
				}
			}
		}

		parent::verifyMockObjects();
	}


	public function getInvocations($stub, $method, $invocationIndex = null, $argumentIndex = null) {
		$callObjects = $stub->getProphecy()->findProphecyMethodCalls($method, new Arg\ArgumentsWildcard([Arg::cetera()]));
		$invocations = [];

		foreach ($callObjects as $callObject) {
			$invocations[] = $callObject->getArguments();
		}

		if (null !== $invocationIndex) {

			$objMethod     = $stub->__prophecyOrigClass . '::' . $method . '()';
			$invocationNum = $invocationIndex + 1;

			if (!isset($invocations[$invocationIndex])) {
				throw new \Exception($objMethod . ' was not invoked ' . $invocationNum . ' times.');

			} else {

				$invocation = $invocations[$invocationIndex];

				if (null !== $argumentIndex) {

					if (!isset($invocation[$argumentIndex])) {
						throw new \Exception("$objMethod invocation $invocationNum had no argument at index $argumentIndex.");

					} else {
						return $invocation[$argumentIndex];
					}

				} else {
					return $invocation;
				}
			}
		}

		return $invocations;
	}


	public function tearDown() {
		$this->mockExpectations = [];
		parent::tearDown();
	}


	protected function transformClass($class) {
		return $class;
	}


	protected function argumentsMatch($actual, $expected) {
		if ($actual === $expected) {
			return true;

		} else {
			foreach ($expected as $index => $expectedArg) {
				if ($expectedArg !== '*' && $expectedArg !== $actual[$index]) {
					return false;
				}
			}
		}

		return true;
	}


	protected function isShortReturnSyntax($methodReturns) {
		if (!is_array($methodReturns)) {
			return true;

		} else if ($this->isAssoc($methodReturns)) {
			return true;

		} else if (count($methodReturns) === 0) {
			return true;

		} else if (!is_array($methodReturns[0])) {
			return true;

		} else {
			return false;
		}
	}


	protected function isAssoc($array) {
		if (!is_array($array)) {
			return false;
		}
		return array_keys($array) !== range(0, count($array) - 1);
	}


	protected function getArgHash($class, $returns) {
		$hashArray = [$class];

		foreach ($returns as $return) {
			if (!is_object($return)) {
				$hashArray[] = $return;
			} else {
				$hashArray[] = array_merge(json_decode(json_encode($return), true), [get_class($return)]);
			}
		}

		return json_encode($hashArray);
	}


	// override this function with your own formatter if you want
	protected function getArgDump($args) {
		return "\n" . print_r($args, true);
	}
}
