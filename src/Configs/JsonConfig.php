<?php

namespace Khill\Lavacharts\Configs;

use \Khill\Lavacharts\Utils;
use \Khill\Lavacharts\Configs\Options;
use \Khill\Lavacharts\Exceptions\InvalidConfigValue;
use \Khill\Lavacharts\Exceptions\InvalidConfigProperty;

/**
 * JsonConfig Object
 *
 * The parent object for all config objects. Adds JsonSerializable and methods for setting options.
 *
 *
 * @package    Lavacharts
 * @subpackage Configs
 * @since      3.0.0
 * @author     Kevin Hill <kevinkhill@gmail.com>
 * @copyright  (c) 2015, KHill Designs
 * @link       http://github.com/kevinkhill/lavacharts GitHub Repository Page
 * @link       http://lavacharts.com                   Official Docs Site
 * @license    http://opensource.org/licenses/MIT MIT
 */
class JsonConfig implements \JsonSerializable
{
    /**
     * Allowed options to set for the UI.
     *
     * @var \Khill\Lavacharts\Configs\Options
     */
    protected $options;

    /**
     * Creates a new JsonConfig object
     *
     * @param  \Khill\Lavacharts\Configs\Options $options
     * @param  array                             $config
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigProperty
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     */
    public function __construct(Options $options, $config = [])
    {
        if (is_array($config) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . __FUNCTION__,
                'array'
            );
        }

        $this->options = $options;

        if (empty($config) === false) {
            $this->parseConfig($config);
        }
    }

    /**
     * Get the value of a set option via magic method through UI.
     *
     * @param  string $option Name of option.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigProperty
     * @return mixed
     */
    public function __get($option)
    {
        return $this->options->get($option);
    }

    private function parseConfig($config)
    {
        foreach ($config as $option => $value) {
            if ($this->options->hasOption($option) === false) {
                throw new InvalidConfigProperty(
                    static::TYPE,
                    __FUNCTION__,
                    $option,
                    $this->options->getDefaults()
                );
            }

            call_user_func([$this, $option], $value);
        }
    }

    /**
     * Sets the value of a string option.
     *
     * @param  string $option Option to set.
     * @param  string $value Value of the option.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidOption
     * @return self
     */
    protected function setStringOption($option, $value)
    {
        if (Utils::nonEmptyString($value) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . $option,
                'string'
            );
        }

        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Sets the value of a string option from an array of choices.
     *
     * @param  string $option Option to set.
     * @param  string $value Value of the option.
     * @param  array  $validValues Array of valid values
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidOption
     * @return self
     */
    protected function setStringInArrayOption($option, $value, $validValues = [])
    {
        if (Utils::nonEmptyStringInArray($value, $validValues) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . $option,
                'string. Whose value is one of '.Utils::arrayToPipedString($validValues)
            );
        }

        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Sets the value of an integer option.
     *
     * @param  string $option Option to set.
     * @param  int    $value Value of the option.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidOption
     * @return self
     */
    protected function setIntOption($option, $value)
    {
        if (is_int($value) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . $option,
                'int'
            );
        }

        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Sets the value of an integer or float option.
     *
     * @param  string $option Option to set.
     * @param  int|float $value Value of the option.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidOption
     * @return self
     */
    protected function setNumericOption($option, $value)
    {
        if (is_numeric($value) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . $option,
                'int|float'
            );
        }

        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Sets the value of an integer option.
     *
     * @param  string $option Option to set.
     * @param  int    $value Value of the option.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidOption
     * @return self
     */
    protected function setIntOrPercentOption($option, $value)
    {
        if (Utils::isIntOrPercent($value) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . $option,
                'int or a string representing a percent.'
            );
        }

        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Sets the value of an boolean option.
     *
     * @param  string $option Option to set.
     * @param  bool   $value Value of the option.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidOption
     * @return self
     */
    protected function setBoolOption($option, $value)
    {
        if (is_bool($value) === false) {
            throw new InvalidConfigValue(
                static::TYPE . '->' . $option,
                'bool'
            );
        }

        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Shortcut method to set the value of an option and return $this.
     *
     * @param  string $option Option to set.
     * @param  mixed $value Value of the option.
     * @return self
     */
    protected function setOption($option, $value)
    {
        $this->options->set($option, $value);

        return $this;
    }

    /**
     * Gets the Options object for the JsonConfig
     *
     * @return \Khill\Lavacharts\Configs\Options
     */
    protected function getOptions()
    {
        return $this->options;
    }

    /**
     * Custom serialization of the JsonConfig object.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->options->getValues();
    }
}