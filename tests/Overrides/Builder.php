<?php

namespace Tests\Overrides;

class Builder extends \Illuminate\Database\Query\Builder
{
    use BuilderHelpers;

    public function update(array $values, array $options = [])
    {
        if (data_get($options, 'upsert') === true) {
            return parent::updateOrInsert($this->compileWheres(), $this->parseValues($values));
        }

        return parent::update($values);
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column === 'tags') {
            return parent::where($column, 'like', "%$operator%");
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function first($columns = ['*'])
    {
        $result = parent::first($columns);
        
        // Handle both object and array scenarios for MongoDB v5 compatibility
        if (is_object($result)) {
            // Convert the object to an object with the same properties but with values parsed
            $resultArray = (array)$result;
            $parsedArray = $this->parseValues($resultArray);
            
            // Create a new stdClass object and set properties
            $parsed = new \stdClass();
            foreach ($parsedArray as $key => $value) {
                $parsed->$key = $value;
            }
            
            return $parsed;
        }
        
        // Legacy support for array results
        return $this->parseValues((array)$result);
    }
}
