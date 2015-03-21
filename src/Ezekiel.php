<?php
namespace NetRivet\Ezekiel;


use Prophecy\Argument as Arg;


trait Ezekiel {

	public static $__cachedStubs = [];
	protected $recordedCalls = [];


	public function stub($class, $returns = []) {
		$isMock = false;
		$hash   = $this->getArgHash($class, $returns);

		if (isset(self::$__cachedStubs[$hash])) {
			return self::$__cachedStubs[$hash];
		}

		$class = $this->transformClass($class);

		$prophecy  = $this->prophesize($class);
		$intercept = method_exists($this, 'interceptStub');

		foreach ($returns as $method => $methodReturns) {

			if ($intercept) {
				$this->interceptStub($class, $method, $methodReturns, $prophecy);
			}

			if ($methodReturns === '~self') {
				$prophecy->{$method}(Arg::any())->willReturn($prophecy);

			} else {

				if ($this->isShortReturnSyntax($methodReturns)) {
					$methodReturns = [['with' => '*', 'returns' => $methodReturns]];

					if ($methodReturns[0]['returns'] === '~neverCalled') {
						$methodReturns = [['expectArgs' => '*', 'times' => 0]];
						$isMock = true;
					}
				}

				foreach ($methodReturns as $index => $return) {

					if (isset($return['expectArgs'])) {
						$methodReturns[$index]['with'] = $return['expectArgs'];
						$expectedArgs = [];
						$isMock = true;

						if ($return['expectArgs'] === '*') {
							$expectedArgs[] = Arg::cetera();

						} else {
							foreach ((array) $return['expectArgs'] as $expected) {
								$expectedArgs[] = $expected === '*' ? '*' : $expected;
							}
						}

						$mockClass = get_class($prophecy->reveal());

						if (!isset($this->recordedCalls[$mockClass])) {
							$this->recordedCalls[$mockClass] = ['prophecy' => $prophecy, 'expectedInvocations' => []];
						}

						if (!isset($this->recordedCalls[$mockClass]['expectedInvocations'][$method])) {
							$this->recordedCalls[$mockClass]['expectedInvocations'][$method] = [];
						}

						$this->recordedCalls[$mockClass]['expectedInvocations'][$method][] = [
							'arguments' => $expectedArgs,
							'times'     => isset($return['times']) ? $return['times'] : '*',
						];
					}
				}

				$that = $this;
				$prophecy->{$method}(Arg::cetera())->will(function ($args) use ($methodReturns, $prophecy, $method, $that) {

					foreach ($methodReturns as $return) {

						if (isset($return['andReturn'])) {
							$return['returns'] = $return['andReturn'];
						}

						if (!isset($return['returns'])) {
							$return['returns'] = null;
						}

						$return['with'] = (array) $return['with'];

						if (self::argumentsMatch($args, $return['with'])) {
							if ($returnArg = self::returnArg($return['returns'])) {
								if ($returnArg['pipe']) {
									return call_user_func_array($returnArg['pipe'], [$args[$returnArg['num']]]);
								} else {
									return $args[$returnArg['num']];
								}

							} else if ($return['returns'] === '~self') {
								return $prophecy;

							} else if (is_string($return['returns']) && strpos($return['returns'], '~joinArgs') === 0) {
								if (strpos($return['returns'], '|') !== false) {
									$delimiter = str_replace('~joinArgs|', '', $return['returns']);
								} else {
									$delimiter = '';
								}

								return join($delimiter, $args);

							} else if (is_string($return['returns']) && strpos($return['returns'], '@') === 0) {
								$prop = preg_replace('/^@/', '', $return['returns']);
								$prop = property_exists($that, $prop) ? $that->{$prop} : $return['returns'];

								if (is_object($prop) && $prop instanceof \Closure) {
									return call_user_func_array($prop, $args);

								} else {
									return $prop;
								}

							} else if (is_object($return['returns']) && $return['returns'] instanceof \Closure) {
								return call_user_func_array($return['returns'], $args);

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

		if (!$isMock) {
			self::$__cachedStubs[$hash] = $stub;
		}

		return $stub;
	}


	public function verifyMockObjects() {
		foreach ((array) $this->recordedCalls as $className => $mock) {

			foreach ($mock['expectedInvocations'] as $method => $expectedInvocations) {

				$objMethod         = $mock['prophecy']->__prophecyOrigClass . '::' . $method . '()';
				$actualInvocations = $mock['prophecy']->findProphecyMethodCalls($method, new Arg\ArgumentsWildcard([Arg::cetera()]));


				foreach ($expectedInvocations as $expectedInvocation) {
					if ($expectedInvocation['times'] === 0 && count($actualInvocations) > 0) {
						throw new \PHPUnit_Framework_ExpectationFailedException(sprintf(
							'%s expected never but called %s time%s',
							$objMethod,
							count($actualInvocations),
							count($actualInvocations) > 1 ? 's' : ''
						));
					}


					$matchedInvocations   = 0;
					$actualInvocationArgs = [];

					foreach ($actualInvocations as $actualInvocation) {
						$actualArguments        = $actualInvocation->getArguments();
						$actualInvocationArgs[] = $actualArguments;
						if (self::argumentsMatch($actualInvocation->getArguments(), $expectedInvocation['arguments'])) {
							$matchedInvocations++;
						}
					}


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

					} else if ($matchedInvocations === 0 && $expectedInvocation['times'] === 0) {
						$this->addToAssertionCount(1);

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


	public function calls($stub, $method, $invocationIndex = null, $argumentIndex = null) {
		$callObjects = $stub->getProphecy()->findProphecyMethodCalls($method, new Arg\ArgumentsWildcard([Arg::cetera()]));
		$invocations = [];

		foreach ($callObjects as $callObject) {
			$invocations[] = $callObject->getArguments();
		}

		if (null !== $invocationIndex) {
			if ('~last' === $invocationIndex) {
				$invocationIndex = count($invocations) - 1;
			}

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
		$this->recordedCalls = [];
		parent::tearDown();
	}


	protected function transformClass($class) {
		return $class;
	}


	protected static function argumentsMatch($actual, $expected) {
		if ($expected === ['*']) {
			return true;

		} else if (count($expected) === 1 && is_object($expected[0]) && get_class($expected[0]) === 'Prophecy\Argument\Token\AnyValuesToken') {
			return true;

		} else if ($actual === $expected) {
			return true;

		} else {
			foreach ($expected as $index => $expectedArg) {
				if ($expectedArg === '*') {
					continue;
				} else if (!isset($actual[$index])) {
					return false;
				} else if ($expectedArg !== $actual[$index]) {
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
		$hashArray = ['class' => $class];

		foreach ($returns as $index => $return) {
			if (is_string($return) && strpos($return, '@') === 0) {
				$prop = preg_replace('/^@/', '', $return);
				if (property_exists($this, $prop)) {
					$hashArray['i_' . $index] = $this->getArgHash('@', [$this->{$prop}]);
				} else {
					$hashArray['i_' . $index] = $prop;
				}
			} else if (!is_object($return)) {
				$hashArray['i_' . $index] = $return;
			} else if (get_class($return) === 'Closure') {
				$hashArray['i_' . $index] = spl_object_hash($return);
			} else {
				$hashArray['i_' . $index] = array_merge(json_decode(json_encode($return), true), [get_class($return)]);
			}
		}

		return json_encode( $hashArray);
	}


	protected static function returnArg($methodReturns) {
		if (!is_string($methodReturns)) {
			return false;
		} else if (strpos($methodReturns, '~firstArg') !== 0 && strpos($methodReturns, '~arg=') !== 0) {
			return false;
		}

		$num  = 0;
		$pipe = false;

		if (strpos($methodReturns, '~arg=') === 0) {
			preg_match("/~arg=([0-9]+)/", $methodReturns, $matches);
			$num = (int) $matches[1] - 1;
		}

		if (strpos($methodReturns, ' -> ') !== false) {
			$parts = explode(' -> ', $methodReturns);
			$pipe  = end($parts);
		}

		return compact('num', 'pipe');
	}


	// override this function with your own formatter if you want
	protected function getArgDump($args) {
		return "\n" . print_r($args, true);
	}
}
