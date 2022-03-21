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
        $result = (array)parent::first($columns);

        return $this->parseValues($result);
    }
}
