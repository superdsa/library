<?php namespace October\Rain\Database\Relations;

use October\Rain\Database\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as CollectionBase;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyBase;

/**
 * HasMany
 *
 * @package october\database
 * @author Alexey Bobkov, Samuel Georges
 */
class HasMany extends HasManyBase
{
    use HasOneOrMany;
    use DefinedConstraints;

    /**
     * __construct a new has many relationship instance.
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey, $relationName = null)
    {
        $this->relationName = $relationName;

        parent::__construct($query, $parent, $foreignKey, $localKey);

        $this->addDefinedConstraints();
    }

    /**
     * setSimpleValue helper for setting this relationship using various expected
     * values. For example, $model->relation = $value;
     */
    public function setSimpleValue($value)
    {
        // Nulling the relationship
        if (!$value) {
            if ($this->parent->exists) {
                $this->parent->bindEventOnce('model.afterSave', function () {
                    $this->update([$this->getForeignKeyName() => null]);
                });
            }
            return;
        }

        if ($value instanceof Model) {
            $value = new Collection([$value]);
        }

        if ($value instanceof CollectionBase) {
            $collection = $value;

            if ($this->parent->exists) {
                $collection->each(function ($instance) {
                    $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
                });
            }
        }
        else {
            $collection = $this->getRelated()->whereIn($this->localKey, (array) $value)->get();
        }

        if (!$collection) {
            return;
        }

        $this->parent->setRelation($this->relationName, $collection);

        $this->parent->bindEventOnce('model.afterSave', function() use ($collection) {
            // Relation is already set, do nothing. This prevents the relationship
            // from being nulled below and left unset because the save will ignore
            // attribute values that are numerically equivalent (not dirty).
            $collection = $collection->reject(function ($instance) {
                return $instance->getOriginal($this->getForeignKeyName()) == $this->getParentKey();
            });

            $existingIds = $collection->pluck($this->localKey)->all();
            $this->whereNotIn($this->localKey, $existingIds)
                ->update([$this->getForeignKeyName() => null]);

            $collection->each(function($instance) {
                $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
                $instance->save(['timestamps' => false]);
            });
        });
    }

    /**
     * getSimpleValue helper for getting this relationship simple value,
     * generally useful with form values.
     */
    public function getSimpleValue()
    {
        $value = null;
        $relationName = $this->relationName;

        if ($relation = $this->parent->$relationName) {
            $value = $relation->pluck($this->localKey)->all();
        }

        return $value;
    }
}
