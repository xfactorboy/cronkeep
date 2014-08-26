<?php

namespace models\Crontab\Job;

/**
 * Expression model.
 * 
 * @method Expression setMinute(int|string|array $minute)
 * @method Expression addMinute(int|string|array $minute)
 * @method array getMinute()
 * @method Expression setHour(int|string|array $hour)
 * @method Expression addHour(int|string|array $hour)
 * @method array getHour()
 * @method Expression setDayOfMonth(int|string|array $dayOfMonth)
 * @method Expression addDayOfMonth(int|string|array $dayOfMonth)
 * @method array getDayOfMonth()
 * @method Expression setMonth(int|string|array $month)
 * @method Expression addMonth(int|string|array $month)
 * @method array getMonth()
 * @method Expression setDayOfWeek(int|string|array $dayOfWeek)
 * @method Expression addDayOfWeek(int|string|array $dayOfWeek)
 * @method array getDayOfWeek()
 * 
 * @author Bogdan Ghervan <bogdan.ghervan@gmail.com>
 */
class Expression
{
	const MINUTE       = 'minute';
	const HOUR	       = 'hour';
	const DAY_OF_MONTH = 'dayOfMonth';
	const MONTH        = 'month';
	const DAY_OF_WEEK  = 'dayOfWeek';
	
	/**
	 * The component parts of a cron expression.
	 * 
	 * @var array
	 */
	protected $_parts = array(
		self::MINUTE       => array(),
		self::HOUR         => array(),
		self::DAY_OF_MONTH => array(),
		self::MONTH        => array(),
		self::DAY_OF_WEEK  => array()
	);
	
	/**
	 * Minimum and maximum allowed value for every part of a cron expression.
	 * 
	 * @var array
	 */
	protected $_bounds = array(
		self::MINUTE       => array('min' => 0, 'max' => 59),
		self::HOUR         => array('min' => 0, 'max' => 23),
		self::DAY_OF_MONTH => array('min' => 0, 'max' => 31),
		self::MONTH        => array('min' => 1, 'max' => 12),
		self::DAY_OF_WEEK  => array('min' => 0, 'max' => 7)
	);
	
	/**
	 * Appends $value to given $part.
	 * 
	 * Example:
	 * <code>
	 * $expression = new Expression();
	 * $expression->addPart(Expression::MINUTE, array('min' =>  0, 'max' => 29, 'step' => 5));
	 * $expression->addPart(Expression::MINUTE, array('min' => 30, 'max' => 59, 'step' => 10));
	 * $expression->addPart(Expression::MINUTE, 7);
	 * $expression->addPart(Expression::MINUTE, array(0, 15, 30, 45));
	 * </code>
	 * Resulting expression thus far: 0-29/5,30-59/10,7,0,15,30,45 * * * *
	 * 
	 * @param string $part
	 * @param int|string|array $value
	 * @return \models\Crontab\Job\Expression
	 * @throws \OutOfBoundsException
	 * @throws \InvalidArgumentException
	 */
	public function addPart($part, $value)
	{
		if (!array_key_exists($part, $this->_parts)) {
			throw new \OutOfBoundsException(__METHOD__ . ' called with an invalid part: ' . $part);
		}
		
		// Value is a number
		if (is_numeric($value)) {
			if ($value < $this->_bounds[$part]['min'] || $value > $this->_bounds[$part]['max']) {
				throw new \InvalidArgumentException('Value is outside the valid range for ' . $part);
			}
			
			$this->_parts[$part][] = (int) $value;
		
		// Value is literal
		} elseif (is_string($value)) {
			// we may want to normalize $value in the future
			if ($value == '*') {
				$this->_parts[$part] = array();
			} else {
				$this->_parts[$part][] = $value;
			}
		
		// Value is a list or a range
		} elseif (is_array($value)) {
			// Value is a range
			if (isset($value['min']) && isset($value['max'])) {
				if (!is_numeric($value['min'])) {
					throw new \InvalidArgumentException(__METHOD__ . ' called with an invalid value for min');
				}
				if ($value['min'] < $this->_bounds[$part]['min']
						|| $value['min'] > $this->_bounds[$part]['max']) {
					throw new \InvalidArgumentException('Value is outside the valid range for ' . $part);
				}
				if (!is_numeric($value['max'])) {
					throw new \InvalidArgumentException(__METHOD__ . ' called with an invalid value for max');
				}
				if ($value['max'] < $this->_bounds[$part]['min']
						|| $value['max'] > $this->_bounds[$part]['max']) {
					throw new \InvalidArgumentException('Value is outside the valid range for ' . $part);
				}
				if (isset($value['step']) && !is_numeric($value['step'])) {
					throw new \InvalidArgumentException(__METHOD__ . ' called with an invalid value for step');
				}
				
				$this->_parts[$part][] = array(
					'min'  => (int) $value['min'],
					'max'  => (int) $value['max'],
					'step' => isset($value['step']) ? (int) $value['step'] : 1
				);
				
			// Value is a list
			} else {
				foreach ($value as $item) {
					$this->addPart($part, $item);
				}
			}
		} else {
			throw new \InvalidArgumentException(__METHOD__ . ' called with an invalid value');
		}
		
		return $this;
	}
	
	/**
	 * Sets $value for $part. Any previous value of $part is lost.
	 * 
	 * Example:
	 * <code>
	 * $expression = new Expression();
	 * $expression->addPart(Expression::HOUR, array(9, 12, 15));
	 * </code>
	 * Resulting expression thus far: * 9,12,15 * * *
	 * 
	 * <code>
	 * $expression->setPart(Expression::HOUR, array(20, 22));
	 * </code>
	 * Resulting expression thus far: * 20,22 * * *
	 * 
	 * @param string $part
	 * @param int|string|array $value
	 * @return Expression
	 * @throws \OutOfBoundsException
	 * @throws \InvalidArgumentException
	 */
	public function setPart($part, $value)
	{
		// Reset part
		$this->_parts[$part] = array();
		
		// Add part as usual
		$this->addPart($part, $value);
		
		return $this;
	}
	
	/**
	 * Renders expression.
	 * 
	 * @return string
	 */
	public function render()
	{
		$expr = array();
		foreach (array_keys($this->_parts) as $part) {
			$expr[] = $this->_renderPart($part);
		}
		
		return implode(' ', $expr);
	}
	
	/**
	 * Overloads method access.
	 * 
	 * Allows the following method calls:
	 * - setMinute($minute)
	 * - addMinute($minute)
	 * - getMinute()
	 * - setHour($hour)
	 * - addHour($hour)
	 * - getHour()
	 * - setDayOfMonth($dayOfMonth)
	 * - addDayOfMonth($dayOfMonth)
	 * - getDayOfMonth()
	 * - setMonth($month)
	 * - addMonth($month)
	 * - getMonth()
	 * - setDayOfWeek($dayOfWeek)
	 * - addDayOfWeek($dayOfWeek)
	 * - getDayOfWeek()
	 * 
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 * @throws \BadMethodCallException
	 */
	public function __call($method, $args)
	{
		$partNames = implode('|', array_keys($this->_parts));
		if (preg_match("/^(?P<action>set|get|add)(?P<part>$partNames)$/i", $method, $matches)) {
			extract($matches);
			$part = lcfirst($part);
			
			switch ($action) {
				case 'get': {
					return $this->_parts[$part];
				}
				case 'set': {
					if (!$args) {
						throw new \BadMethodCallException('Method ' . $method . ' called without a value');
					}
					return $this->setPart($part, $args[0]);
				}
				case 'add': {
					if (!$args) {
						throw new \BadMethodCallException('Method ' . $method . ' called without a value');
					}
					return $this->addPart($part, $args[0]);
				}
			}
		} else {
			throw new \BadMethodCallException('Call to undefined method ' . $method);
		}
	}
	
	/**
	 * Returns string representation of this object.
	 * This is an alias for @see Expression::render.
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return $this->render();
	}
	
	/**
	 * Renders given part.
	 * 
	 * @param string $part
	 * @return string
	 */
	protected function _renderPart($part)
	{
		$expr = array();
		foreach ($this->_parts[$part] as $value) {
			if (is_array($value)) {
				$step = $value['step'] > 1 ? '/' . $value['step'] : '';
				$expr[] = sprintf('%s-%s%s', $value['min'], $value['max'], $step);
			} else {
				$expr[] = $value;
			}
		}
		
		return $expr ? implode(',', $expr) : '*';
	}
}