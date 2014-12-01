<?php namespace Analogue\ORM\Relationships;

use Analogue\ORM\System\Query;
use Analogue\ORM\System\Mapper;
use Analogue\ORM\EntityCollection;
use Illuminate\Database\Query\Expression;

class BelongsTo extends Relationship {

	/**
	 * The foreign key of the parent model.
	 *
	 * @var string
	 */
	protected $foreignKey;

	/**
	 * The associated key on the parent model.
	 *
	 * @var string
	 */
	protected $otherKey;

	/**
	 * The name of the relationship.
	 *
	 * @var string
	 */
	protected $relation;

	/**
	 * Indicate if the parent entity hold the key for the relation.
	 * 
	 * @var boolean
	 */
	protected static $ownForeignKey = true;

	/**
	 * Create a new belongs to relationship instance.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @param  \Illuminate\Database\Eloquent\Model  $parent
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relation
	 * @return void
	 */
	public function __construct(Mapper $mapper, $parent, $foreignKey, $otherKey, $relation)
	{
		$this->otherKey = $otherKey;
		$this->relation = $relation;
		$this->foreignKey = $foreignKey;

		parent::__construct($mapper, $parent);
	}
	
	public function attachTo($related)
	{
		return $this->associate($related);
	}

	public function detachFrom($related)
	{
		return $this->dissociate($related);
	}
	
	/**
	 * Get the results of the relationship.
	 *
	 * @return mixed
	 */
	public function getResults($relation)
	{
		$result = $this->query->first();

		$this->cacheRelation($result, $relation);

		return $result;
	}

	/**
	 * Set the base constraints on the relation query.
	 *
	 * @return void
	 */
	public function addConstraints()
	{
		if (static::$constraints)
		{
			// For belongs to relationships, which are essentially the inverse of has one
			// or has many relationships, we need to actually query on the primary key
			// of the related models matching on the foreign key that's on a parent.
			$table = $this->relatedMap->getTable();

			$this->query->where($table.'.'.$this->otherKey, '=', $this->parent->{$this->foreignKey});
		}
	}

	/**
	 * Add the constraints for a relationship count query.
	 *
	 * @param  \Analogue\ORM\System\Query  $query
	 * @param  \Analogue\ORM\System\Query  $parent
	 * @return \Analogue\ORM\System\Query
	 */
	public function getRelationCountQuery(Query $query, Query $parent)
	{
		$query->select(new Expression('count(*)'));

		$otherKey = $this->wrap($query->getTable().'.'.$this->otherKey);

		return $query->where($this->getQualifiedForeignKey(), '=', new Expression($otherKey));
	}

	/**
	 * Set the constraints for an eager load of the relation.
	 *
	 * @param  array  $entities
	 * @return void
	 */
	public function addEagerConstraints(array $entities)
	{
		// We'll grab the primary key name of the related models since it could be set to
		// a non-standard name and not "id". We will then construct the constraint for
		// our eagerly loading query so it returns the proper models from execution.
		$key = $this->relatedMap->getTable().'.'.$this->otherKey;

		$this->query->whereIn($key, $this->getEagerModelKeys($entities));
	}

	/**
	 * Gather the keys from an array of related models.
	 *
	 * @param  array  $entities
	 * @return array
	 */
	protected function getEagerModelKeys(array $entities)
	{
		$keys = array();

		// First we need to gather all of the keys from the parent models so we know what
		// to query for via the eager loading query. We will add them to an array then
		// execute a "where in" statement to gather up all of those related records.
		foreach ($entities as $entity)
		{
			if ( ! is_null($value = $entity->{$this->foreignKey}))
			{
				$keys[] = $value;
			}
		}

		// If there are no keys that were not null we will just return an array with 0 in
		// it so the query doesn't fail, but will not return any results, which should
		// be what this developer is expecting in a case where this happens to them.
		if (count($keys) == 0)
		{
			return array(0);
		}

		return array_values(array_unique($keys));
	}

	/**
	 * Initialize the relation on a set of models.
	 *
	 * @param  array   $models
	 * @param  string  $relation
	 * @return array
	 */
	public function initRelation(array $entities, $relation)
	{
		foreach ($entities as $entity)
		{
			$entity->setEntityAttribute($relation, null);
		}

		return $entities;
	}

	/**
	 * Match the eagerly loaded results to their parents.
	 *
	 * @param  array   $entities
	 * @param  \Analogue\ORM\EntityCollection  $results
	 * @param  string  $relation
	 * @return array
	 */
	public function match(array $entities, EntityCollection $results, $relation)
	{
		$foreign = $this->foreignKey;

		$other = $this->otherKey;

		// First we will get to build a dictionary of the child models by their primary
		// key of the relationship, then we can easily match the children back onto
		// the parents using that dictionary and the primary key of the children.
		$dictionary = [];

		// Not use caching this.
		//$cache = [];

		$parentKey = $this->parentMap->getKeyName();

		foreach ($results as $result)
		{
			$dictionary[$result->getEntityAttribute($other)] = $result;
		}

		// Once we have the dictionary constructed, we can loop through all the parents
		// and match back onto their children using these keys of the dictionary and
		// the primary key of the children to map them onto the correct instances.
		foreach ($entities as $entity)
		{
			if (isset($dictionary[$entity->$foreign]))
			{
				$entity->setEntityAttribute($relation, $dictionary[$entity->$foreign]);

				// Not use caching this.
				//$cache[$entity->$parentKey] = $entity->$foreign;
			}
		}

		// Not use caching this.
		//$this->parentMapper->getEntityCache()->cacheLoadedRelation($relation, $cache);

		return $entities;
	}

	/**
	 * Associate the model instance to the given parent.
	 *
	 * @param  mixed $entity
	 * @return void
	 */
	public function associate($entity)
	{
		$this->parent->setEntityAttribute($this->foreignKey, $entity->getEntityAttribute($this->otherKey));
	}

	/**
	 * Dissociate previously associated model from the given parent.
	 *
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function dissociate()
	{
		$this->parent->setEntityAttribute($this->foreignKey, null);
	}

	/**
	 * Get the foreign key of the relationship.
	 *
	 * @return string
	 */
	public function getForeignKey()
	{
		return $this->foreignKey;
	}

	/**
	 * Get the fully qualified foreign key of the relationship.
	 *
	 * @return string
	 */
	public function getQualifiedForeignKey()
	{
		return $this->parentMap->getTable().'.'.$this->foreignKey;
	}

	/**
	 * Get the associated key of the relationship.
	 *
	 * @return string
	 */
	public function getOtherKey()
	{
		return $this->otherKey;
	}

	/**
	 * Get the fully qualified associated key of the relationship.
	 *
	 * @return string
	 */
	public function getQualifiedOtherKeyName()
	{
		return $this->relatedMap->getTable().'.'.$this->otherKey;
	}
}