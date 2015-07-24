<?php

namespace Khill\Lavacharts\Datatables;

use \Carbon\Carbon;
use \Khill\Lavacharts\Utils;
use \Khill\Lavacharts\Datatables\Rows\RowFactory;
use \Khill\Lavacharts\Datatables\Columns\Role;
use \Khill\Lavacharts\Datatables\Columns\Column;
use \Khill\Lavacharts\Datatables\Columns\ColumnFactory;
use \Khill\Lavacharts\Datatables\Formats\Format;
use \Khill\Lavacharts\Exceptions\InvalidDate;
use \Khill\Lavacharts\Exceptions\InvalidCellCount;
use \Khill\Lavacharts\Exceptions\InvalidConfigValue;
use \Khill\Lavacharts\Exceptions\InvalidConfigProperty;
use \Khill\Lavacharts\Exceptions\InvalidColumnDefinition;
use \Khill\Lavacharts\Exceptions\InvalidColumnIndex;
use \Khill\Lavacharts\Exceptions\InvalidRowDefinition;
use \Khill\Lavacharts\Exceptions\InvalidRowProperty;

/**
 * DataTable Object
 *
 * The DataTable object is used to hold the data passed into a visualization.
 * A DataTable is a basic two-dimensional table. All data in each column must
 * have the same data type. Each column has a descriptor that includes its data
 * type, a label for that column (which might be displayed by a visualization),
 * and an ID, which can be used to refer to a specific column (as an alternative
 * to using column indexes). The DataTable object also supports a map of
 * arbitrary properties assigned to a specific value, a row, a column, or the
 * whole DataTable. Visualizations can use these to support additional features;
 * for example, the Table visualization uses custom properties to let you assign
 * arbitrary class names or styles to individual cells.
 *
 *
 * @package    Lavacharts
 * @subpackage Datatables
 * @author     Kevin Hill <kevinkhill@gmail.com>
 * @copyright  (c) 2015, KHill Designs
 * @link       http://github.com/kevinkhill/lavacharts GitHub Repository Page
 * @link       http://lavacharts.com                   Official Docs Site
 * @license    http://opensource.org/licenses/MIT MIT
 */
class Datatable implements \JsonSerializable
{
    /**
     * Google's datatable version
     *
     * @var string
     */
    const VERSION = '0.6';

    /**
     * Google's visualization class name.
     *
     * @var string
     */
    const VIZ_CLASS = 'google.visualization.DataTable';

    /**
     * Timezone for dealing with datetime and Carbon objects.
     *
     * @var string
     */
    public $timezone;

    /**
     * The DateTime format used by Carbon when parsing string dates.
     *
     * @var string
     */
    public $dateTimeFormat;

    /**
     * Holds the information defining the columns.
     *
     * @var array
     */
    protected $cols = [];

    /**
     * Holds the information defining each row.
     *
     * @var array
     */
    protected $rows = [];

    /**
     * Holds the formatting information for each column.
     *
     * @var array
     */
    protected $formats = [];

    /**
     * Valid column types.
     *
     * @var array
     */
    protected $columnTypes = [
        'string',
        'number',
        //'bool',
        'date',
        'datetime',
        'timeofday'
    ];

    /**
     * Valid column descriptions
     *
     * @var array
     */
    protected $columnDesc = [
        'type',
        'label',
        'id',
        'role',
        'pattern'
    ];

    /**
     * Creates a new DataTable
     *
     * @access public
     * @param  string    $timezone
     * @return self
     */
    public function __construct($timezone = null)
    {
        $this->setTimezone($timezone);

        $this->rowFactory = new RowFactory($this);
    }

    /**
     * Sets the Timezone that Carbon will use when parsing dates
     *
     * This will use the passed timezone, falling back to the default from php.ini,
     * and falling back from that to America/Los_Angeles
     *
     * @access public
     * @param  string $timezone
     * @return self
     */
    public function setTimezone($timezone)
    {
        // get PHP.ini setting
        $default_timezone = date_default_timezone_get();

        if (Utils::nonEmptyString($timezone)) {
            $this->timezone = $timezone;
        } elseif (Utils::nonEmptyString($default_timezone)) {
            $this->timezone = $default_timezone;
        } else {
            $this->timezone = 'America/Los_Angeles';
        }

        date_default_timezone_set($this->timezone);

        return $this;
    }

    /**
     * Sets the format to be used by Carbon::createFromFormat()
     *
     * This method is used to set the format to be used to parse a string
     * passed to a cell in a date column, that was parsed incorrectly by Carbon::parse()
     *
     * @access public
     * @param  string $dateTimeFormat
     * @return self
     */
    public function setDateTimeFormat($dateTimeFormat)
    {
        if (Utils::nonEmptyString($dateTimeFormat)) {
            $this->dateTimeFormat = $dateTimeFormat;
        } else {
            throw new InvalidConfigValue(
                __FUNCTION__,
                'string'
            );
        }

        return $this;
    }

    /**
     * Returns a clone of the DataTable
     *
     * @access public
     * @since  3.0.0
     * @return \Khill\Lavacharts\Datatables\Datatable;
     */
    public function getClone()
    {
        return clone $this;
    }

    /**
     * Adds a column object to the datatable.
     *
     * @access private
     * @since  3.0.0
     * @param  \Khill\Lavacharts\Datatables\Columns\Column $column;
     * @return self;
     */
    private function addColumnToTable(Column $column)
    {
        $this->cols[] = $column;

        return $this;
    }

    /**
     * Adds a column to the DataTable
     *
     * First signature has the following parameters:
     * type - A string with the data type of the values of the column.
     * The type can be one of the following: 'string' 'number' 'bool' 'date'
     * 'datetime' 'timeofday'.
     *
     * columnLabel - [Optional] A string with the label of the column. The column
     * label is typically displayed as part of the visualization, for example as
     *  a column header in a table, or as a legend label in a pie chart. If not
     * value is specified, an empty string is assigned.
     * optId - [Optional] A string with a unique identifier for the column. If
     * not value is specified, an empty string is assigned.
     *
     *
     * @access public
     * @param  string|array          Column type or an array describing the column.
     * @param  string                A label for the column. (Optional)
     * @param  string                An ID for the column. (Optional)
     * @param  Format                A column formatter object. (Optional)
     * @param  string                A role for the column. (Optional)
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigProperty
     * @return self
     */
    public function addColumn($typeOrDescArr, $label = '', $optId = '', Format $formatter = null, $role = '')
    {
        if (is_array($typeOrDescArr)) {
            call_user_func_array([$this, 'createColumnFromStrings'], $typeOrDescArr);
        } elseif (is_string($typeOrDescArr)) {
            $this->createColumnFromStrings($typeOrDescArr, $label, $optId, $formatter, $role);
        } else {
            throw new InvalidConfigValue(
                __FUNCTION__,
                'string or array'
            );
        }

        return $this;
    }

    /**
     * Adds multiple columns to the DataTable
     *
     * @access public
     * @param  array $arrayOfColumns Array of columns to batch add to the DataTable.
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @return self
     */
    public function addColumns($arrayOfColumns)
    {
        if (Utils::arrayIsMulti($arrayOfColumns) === false) {
            throw new InvalidConfigValue(
                __FUNCTION__,
                'array of arrays'
            );
        }

        foreach ($arrayOfColumns as $columnArray) {
            call_user_func_array([$this, 'createColumnFromStrings'], $columnArray);
        }

        return $this;
    }

    /**
     * Supplemental function to add a string column with less params.
     *
     * @access public
     * @param  string $label A label for the column.
     * @param  \Khill\Lavacharts\Formats\Format $format A column format object. (Optional)
     * @throws \Khill\Lavacharts\Exceptions\InvalidLabel
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnType
     * @return self
     */
    public function addStringColumn($label='', Format $format=null, $role='')
    {
        $column = ColumnFactory::create('string', $label, $format, $role);

        return $this->addColumnToTable($column);
        //return $this->addColumn('string', $label, 'col_' . (count($this->cols) + 1), $formatter);
    }

    /**
     * Supplemental function to add a date column with less params.
     *
     * @access public
     * @param  string $label A label for the column.
     * @param  \Khill\Lavacharts\Formats\Format $format A column format object. (Optional)
     * @throws \Khill\Lavacharts\Exceptions\InvalidLabel
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnType
     * @return self
     */
    public function addDateColumn($label='', Format $format=null, $role='')
    {
        $column = ColumnFactory::create('date', $label, $format, $role);

        return $this->addColumnToTable($column);
        //return $this->addColumn('date', $label, 'col_' . (count($this->cols) + 1), $formatter);
    }

    /**
     * Supplemental function to add a number column with less params.
     *
     * @access public
     * @param  string $label A label for the column.
     * @param  \Khill\Lavacharts\Formats\Format $format A column format object. (Optional)
     * @throws \Khill\Lavacharts\Exceptions\InvalidLabel
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnType
     * @return self
     */
    public function addNumberColumn($label='', Format $format=null, $role='')
    {
        $column = ColumnFactory::create('number', $label, $format, $role);

        return $this->addColumnToTable($column);

        //return $this->addColumn('number', $label, 'col_' . (count($this->cols) + 1), $formatter);
    }

    /**
     * Adds a new column for defining a data role.
     *
     * @access public
     * @since  3.0.0
     * @param  string $type Type of data the column will define.
     * @param  string $role Type of role that the data will represent.
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnType
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnRole
     * @return self
     */
    public function addRoleColumn($type, $role)
    {
        $column = ColumnFactory::create($type, '', null, $role);

        return $this->addColumnToTable($column);
        //return $this->addColumn('string', $label, 'col_' . (count($this->cols) + 1), $formatter);
    }

    /**
     * Supplemental function to add columns from an array.
     *
     * @access protected
     * @param  array $colDefArray
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnDefinition
     * @return self
     */
    protected function XXXXaddColumnFromArray($colDefArray)
    {
        if (Utils::arrayValuesCheck($colDefArray, 'string') && Utils::between(1, count($colDefArray), 5, true)) {
            call_user_func_array([$this, 'createColumnFromStrings'], $colDefArray);
        } else {
            throw new InvalidColumnDefinition($colDefArray);
        }

        return $this;
    }

    /**
     * Supplemental function to create columns from strings.
     *
     * @access protected
     * @param  string  $type
     * @param  string  $label
     * @param  string  $id
     * @param  \Khill\Lavacharts\Datatables\Formats\Format $format
     * @param  string $role
     * @throws \Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @return self
     */
    protected function createColumnFromStrings($type, $label='', $format=null, $role='')
    {
        $column = ColumnFactory::create($type, $label, $format, $role);

        return $this->addColumnToTable($column);
    }

    protected function XXXcreateColumnFromStrings($type, $label='', $id='', $format=null, $role='')
    {
        $colIndex = $this->getColumnCount();

        if (in_array($type, $this->columnTypes) === false) {
            throw new InvalidConfigProperty(
                __FUNCTION__,
                'string',
                Utils::arrayToPipedString($this->columnTypes)
            );
        }

        if (Utils::nonEmptyString($type) !== true) {
            throw new InvalidConfigValue(
                __FUNCTION__,
                'string'
            );
        }

        $descArray['type'] = $type;

        if (Utils::nonEmptyString($label)) {
            $descArray['label'] = $label;
        }

        if (Utils::nonEmptyString($id)) {
            $descArray['id'] = $id;
        }

        if (! is_null($format)) {
            $this->formats[$colIndex] = $format;
        }

        if (Utils::nonEmptyString($role)) {
            $descArray['role'] = $role;
        }

        $this->cols[$colIndex] = $descArray;
    }

    /**
     * Drops a column and its data from the DataTable
     *
     * @param  int $colIndex
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnIndex
     * @return self
     */
    public function dropColumn($colIndex)
    {
        if (is_int($colIndex) === false || array_key_exists($colIndex, $this->cols) === false) {
            throw new InvalidColumnIndex($colIndex, count($this->cols));
        }

        unset($this->cols[$colIndex]);

        $this->cols = array_values($this->cols);

        return $this;
    }

    /**
     * Sets the format of the column.
     *
     * @access public
     * @param  integer $colIndex
     * @param  \Khill\Lavacharts\Formats\Format $formatter
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnIndex
     * @return self
     */
    public function formatColumn($colIndex, Format $formatter)
    {
        if (is_int($colIndex) == false || Utils::between(0, $colIndex, $this->getColumnCount()-1)) {
            throw new InvalidColumnIndex($colIndex, count($this->cols));
        }

        $this->formats[$colIndex] = $formatter->toArray();

        return $this;
    }

    /**
     * Sets the format of multiple columns.
     *
     * @access public
     * @param  array     $colFormatArr
     * @return self
     */
    public function formatColumns($colFormatArr)
    {
        if (is_array($colFormatArr) && ! empty($colFormatArr)) {
            foreach ($colFormatArr as $index => $format) {
                if (is_subclass_of($format, 'Format')) {
                    $this->formats[$colIndex] = $format->toArray();
                }
            }
        }

        return $this;
    }

    /**
     * Add a row to the DataTable
     *
     * Each cell in the table is described by an array with the following properties:
     *
     * v [Optional] The cell value. The data type should match the column data type.
     * If null, the whole object should be empty and have neither v nor f properties.
     *
     * f [Optional] A string version of the v value, formatted for display. The
     * values should match, so if you specify Date(2008, 0, 1) for v, you should
     * specify "January 1, 2008" or some such string for this property. This value
     * is not checked against the v value. The visualization will not use this value
     * for calculation, only as a label for display. If omitted, a string version
     * of v will be used.
     *
     * p [Optional] An object that is a map of custom values applied to the cell.
     * These values can be of any JavaScript type. If your visualization supports
     * any cell-level properties, it will describe them; otherwise, this property
     * will be ignored. Example: p:{style: 'border: 1px solid green;'}.
     *
     *
     * Cells in the row array should be in the same order as their column descriptions
     * in cols. To indicate a null cell, you can specify null, leave a blank for
     * a cell in an array, or omit trailing array members. So, to indicate a row
     * with null for the first two cells, you would specify [null, null, {cell_val}].
     *
     * @access public
     * @param  mixed $optCellArray Array of values or DataCells.
     * @throws \Khill\Lavacharts\Exceptions\InvalidCellCount
     * @return self
     */
    public function addRow($optCellArray = null)
    {
        if (is_null($optCellArray)) {
            $this->rows[] = $this->rowFactory->null();
        } else {
            if (is_array($optCellArray) === false) {
                throw new InvalidRowDefinition($optCellArray);
            }

            if (Utils::arrayIsMulti($optCellArray)) {
                $timeOfDayIndex = $this->getColumnIndexByType('timeofday');

                if ($timeOfDayIndex !== false) {
                    $rowVals = $this->parseTimeOfDayRow($optCellArray);
                } else {
                    $rowVals = $this->parseExtendedCellArray($optCellArray);
                }

                $this->rows[] = ['c' => $rowVals];
            } else {
/*
                $colCount = $this->getColumnCount();
                $rowColCount = count($optCellArray);

                if ($rowColCount > $colCount) {
                    throw new InvalidCellCount($rowColCount, $colCount);
                }

//TODO: Add logic for parsing date cells

                for ($index = 0; $index < $colCount; $index++) {
                    if (isset($optCellArray[$index])) {
                        if ($this->cols[$index]->getType() == 'date' || $this->cols[$index]->getType() == 'datetime') {
                            $rowVals[] = ['v' => $this->parseDate($optCellArray[$index])];
                        } else {
                            $rowVals[] = ['v' => $optCellArray[$index]];
                        }
                    } else {
                        $rowVals[] = ['v' => null];
                    }
                }
*/

                if (empty($optCellArray) === true) {
                    $this->rows[] = $this->rowFactory->null();
                } else {
                    $this->rows[] = $this->rowFactory->create($optCellArray);
                }
                //$this->rows[] = ['c' => $rowVals];
            }
        }

        return $this;
    }

    /**
     * Adds multiple rows to the DataTable.
     *
     * @see    addRow()
     * @access public
     * @param  array Multi-dimensional array of rows.
     * @return self
     */
    public function addRows($arrayOfRows)
    {
        if (Utils::arrayIsMulti($arrayOfRows)) {
            foreach ($arrayOfRows as $row) {
                $this->addRow($row);
            }
        } else {
            throw new InvalidConfigValue(
                __FUNCTION__,
                'array of arrays'
            );
        }

        return $this;
    }

    /**
     * Returns an array of array wrapped null values equal to the
     * number of columns defined.
     *
     * @access protected
     * @return array
     */
    protected function addNullRow()
    {
        for ($a = 0; $a < count($this->cols); $a++) {
            $tmp[] = ['v' => null];
        }

        return ['c' => $tmp];
    }

    /**
     * Returns the rows array from the DataTable
     *
     * @access public
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Returns the number of rows in the DataTable
     *
     * @access public
     * @return int
     */
    public function getRowCount()
    {
        return count($this->rows);
    }

    /**
     * Returns a column based on it's index.
     *
     * @since  3.0.0
     * @access public
     * @param  integer $index
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnIndex
     * @return string
     */
    public function getColumn($index)
    {
        if (is_int($index) === false || isset($this->cols[$index]) === false) {
            throw new InvalidColumnIndex($index, count($this->cols));
        }

        return $this->cols[$index];
    }

    /**
     * Returns the column array from the DataTable
     *
     * @access public
     * @return array
     */
    public function getColumns()
    {
        return $this->cols;
    }

    /**
     * Returns the number of columns in the DataTable
     *
     * @access public
     * @return int
     */
    public function getColumnCount()
    {
        return count($this->cols);
    }

    /**
     * Returns the label of a column based on it's index.
     *
     * @access public
     * @since  3.0.0
     * @param  integer $index
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnIndex
     * @return string
     */
    public function getColumnLabel($index)
    {
        return $this->getColumn($index)['label'];
    }

    /**
     * Returns the type of a column based on it's index.
     *
     * @access public
     * @since  3.0.0
     * @param  integer $index
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnIndex
     * @return string
     */
    public function getColumnType($index)
    {
        return $this->getColumn($index)['type'];
    }

    /**
     * Returns the id of a column based on it's index.
     *
     * @access public
     * @since  3.0.0
     * @param  integer $index
     * @throws \Khill\Lavacharts\Exceptions\InvalidColumnIndex
     * @return string
     */
    public function getColumnId($index)
    {
        return $this->getColumn($index)['id'];
    }

    /**
     * Returns the types of columns currently defined.
     *
     * @since  2.5.2
     * @access public
     * @return array
     */
    public function getColumnTypes()
    {
        foreach($this->cols as $arr) {
            if (array_key_exists('type', $arr)) {
                $colTypes[] = $arr['type'];
            }
        }

        return $colTypes;
        //return array_column($this->getColumns(), 'type');
    }

    /**
     * Returns the labels of columns currently defined.
     *
     * @since  3.0.0
     * @access public
     * @return array
     */
    public function getColumnLabels()
    {
        foreach($this->cols as $arr) {
            if (array_key_exists('label', $arr)) {
                $colTypes[] = $arr['label'];
            }
        }

        return $colTypes;
        //return array_column($this->getColumns(), 'type');
    }

    /**
     * Returns the column number of the ypes of columns currently defined.
     *
     * @since  2.5.2
     * @access public
     * @return int|bool Column index on success, false on failure.
     */
    public function getColumnIndexByType($type)
    {
        return array_search($type, $this->getColumnTypes());
    }

    /**
     * Returns the formats array from the DataTable
     *
     * @access public
     * @return array
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * Boolean value if there are defined formatters
     *
     * @access public
     * @return bool
     */
    public function hasFormats()
    {
        return count($this->formats) > 0 ? true : false;
    }

    /**
     * Convert the DataTable to JSON
     *
     * @access public
     * @return string JSON representation of the DataTable.
     */
    public function toJson()
    {
        return json_encode($this);
    }

    /**
     * Custom serialization of the DataTable.
     *
     * @return array
     */
    public function jsonSerialize() {
        return [
            'cols' => $this->cols,
            'rows' => $this->rows,
        ];
    }

    /**
     * Parses an extended cell definition, as and array defined with v,f,p
     *
     * @access protected
     * @param  array              $cellArray
     * @throws InvalidRowProperty
     * @return array
     */
    protected function parseExtendedCellArray($cellArray)
    {
        foreach ($cellArray as $prop => $value) {
            if (in_array($value, ['v', 'f', 'p']) === false) {
                throw new InvalidRowProperty;
            }

            $rowVals[] = [$prop => $value];
        }

        return $rowVals;
    }

    /**
     * Parses a timeofday row definition.
     *
     * @access protected
     * @param  array   $cellArray
     * @return array
     */
    protected function parseTimeOfDayRow($cellArray)
    {
        foreach ($cellArray as $cell) {
            $rowVals[] = ['v' => $cell];
        }

        return $rowVals;
    }

    /**
     * Either passes the Carbon instance or parses a datetime string.
     *
     * @access protected
     * @param  Carbon|string $date
     * @return string Javscript date declaration
     */
    protected function parseDate($date)
    {
        if (empty($date)) {
            throw new InvalidDate;
        }

        if (is_a($date, 'Carbon\Carbon')) {
            return $this->carbonToJsString($date);
        }

        try {
            if (Utils::nonEmptyString($this->dateTimeFormat)) {
                $carbonDate = Carbon::createFromFormat($this->dateTimeFormat, $date);
            } else {
                $carbonDate = Carbon::parse($date);
            }

            return $this->carbonToJsString($carbonDate);
        } catch (\Exception $e) {
           throw new InvalidDate;
        }
    }

    /**
     * Outputs the Carbon object as a valid javascript Date string.
     *
     * @access protected
     * @return string Javscript date declaration
     */
    protected function carbonToJsString(Carbon $c)
    {
        return sprintf(
            'Date(%d,%d,%d,%d,%d,%d)',
            isset($c->year)   ? $c->year      : 'null',
            isset($c->month)  ? $c->month - 1 : 'null', //silly javascript
            isset($c->day)    ? $c->day       : 'null',
            isset($c->hour)   ? $c->hour      : 'null',
            isset($c->minute) ? $c->minute    : 'null',
            isset($c->second) ? $c->second    : 'null'
        );
    }
}