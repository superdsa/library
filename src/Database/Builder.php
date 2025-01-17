<?php namespace October\Rain\Database;

use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as BuilderModel;
use October\Rain\Support\Facades\DbDongle;

/**
 * Query builder class.
 *
 * Extends Eloquent builder class.
 *
 * @package october\database
 * @author Alexey Bobkov, Samuel Georges
 */
class Builder extends BuilderModel
{
    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        return $this->pluck($column, $key)->all();
    }

    /**
     * Perform a search on this query for term found in columns.
     * @param  string $term  Search query
     * @param  array $columns Table columns to search
     * @param  string $mode  Search mode: all, any, exact.
     * @return self
     */
    public function searchWhere($term, $columns = [], $mode = 'all')
    {
        return $this->searchWhereInternal($term, $columns, $mode, 'and');
    }

    /**
     * Add an "or search where" clause to the query.
     * @param  string $term  Search query
     * @param  array $columns Table columns to search
     * @param  string $mode  Search mode: all, any, exact.
     * @return self
     */
    public function orSearchWhere($term, $columns = [], $mode = 'all')
    {
        return $this->searchWhereInternal($term, $columns, $mode, 'or');
    }

    /**
     * Internal method to apply a search constraint to the query.
     * Mode can be any of these options:
     * - all: result must contain all words
     * - any: result can contain any word
     * - exact: result must contain the exact phrase
     */
    protected function searchWhereInternal($term, $columns, $mode, $boolean)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        if (!$mode) {
            $mode = 'all';
        }

        if ($mode === 'exact') {
            $this->where(function ($query) use ($columns, $term) {
                foreach ($columns as $field) {
                    if (!strlen($term)) {
                        continue;
                    }
                    $fieldSql = $this->query->raw(sprintf("lower(%s)", DbDongle::cast($field, 'TEXT')));
                    $termSql = '%'.trim(mb_strtolower($term)).'%';
                    $query->orWhere($fieldSql, 'LIKE', $termSql);
                }
            }, null, null, $boolean);
        }
        else {
            $words = explode(' ', $term);
            $wordBoolean = $mode === 'any' ? 'or' : 'and';

            $this->where(function ($query) use ($columns, $words, $wordBoolean) {
                foreach ($columns as $field) {
                    $query->orWhere(function ($query) use ($field, $words, $wordBoolean) {
                        foreach ($words as $word) {
                            if (!strlen($word)) {
                                continue;
                            }
                            $fieldSql = $this->query->raw(sprintf("lower(%s)", DbDongle::cast($field, 'TEXT')));
                            $wordSql = '%'.trim(mb_strtolower($word)).'%';
                            $query->where($fieldSql, 'LIKE', $wordSql, $wordBoolean);
                        }
                    });
                }
            }, null, null, $boolean);
        }

        return $this;
    }

    /**
     * paginateAtPage paginates by passing the page number directly
     *
     * @param  int  $perPage
     * @param  int  $currentPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateAtPage($perPage, $currentPage)
    {
        return $this->paginate($perPage, ['*'], 'page', $currentPage);
    }

    /**
     * paginateCustom paginates using a custom page name.
     *
     * @param  int  $perPage
     * @param  string $pageName
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateCustom($perPage, $pageName)
    {
        return $this->paginate($perPage, ['*'], $pageName);
    }

    /**
     * paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string $pageName
     * @param  int  $currentPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $currentPage = null)
    {
        // Legacy signature support
        // paginate($perPage, $currentPage, $columns, $pageName)
        if (!is_array($columns)) {
            $_currentPage = $columns;
            $_columns = $pageName;
            $_pageName = $currentPage;

            $columns = is_array($_columns) ? $_columns : ['*'];
            $pageName = $_pageName !== null ? $_pageName : 'page';
            $currentPage = is_array($_currentPage) ? null : $_currentPage;
        }

        if (!$currentPage) {
            $currentPage = Paginator::resolveCurrentPage($pageName);
        }

        if (!$perPage) {
            $perPage = $this->model->getPerPage();
        }

        $total = $this->toBase()->getCountForPagination();
        $this->forPage((int) $currentPage, (int) $perPage);

        return $this->paginator($this->get($columns), $total, $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName
        ]);
    }

    /**
     * simplePaginateAtPage simply paginates by passing the page number directly
     *
     * @param  int  $perPage
     * @param  int  $currentPage
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginateAtPage($perPage, $currentPage)
    {
        return $this->simplePaginate($perPage, ['*'], 'page', $currentPage);
    }

    /**
     * simplePaginateCustom simply paginates using a custom page name.
     *
     * @param  int  $perPage
     * @param  string $pageName
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginateCustom($perPage, $pageName)
    {
        return $this->simplePaginate($perPage, ['*'], $pageName);
    }

    /**
     * simplePaginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string $pageName
     * @param  int  $currentPage
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $currentPage = null)
    {
        // Legacy signature support
        // paginate($perPage, $currentPage, $columns, $pageName)
        if (!is_array($columns)) {
            $_currentPage = $columns;
            $_columns = $pageName;
            $_pageName = $currentPage;

            $columns = is_array($_columns) ? $_columns : ['*'];
            $pageName = $_pageName !== null ? $_pageName : 'page';
            $currentPage = is_array($_currentPage) ? null : $_currentPage;
        }

        if (!$currentPage) {
            $currentPage = Paginator::resolveCurrentPage($pageName);
        }

        if (!$perPage) {
            $perPage = $this->model->getPerPage();
        }

        $this->skip(($currentPage - 1) * $perPage)->take($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName
        ]);
    }

    /**
     * Dynamically handle calls into the query instance.
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($this->model->methodExists($scope = 'scope'.ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Add a basic where clause to a relationship query.
     *
     * @param  string  $relation
     * @param  \Closure|string|array|\Illuminate\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function whereRelation($relation, $column, $operator = null, $value = null)
    {
        return $this->whereHas($relation, function ($query) use ($column, $operator, $value) {
            $query->where($column, $operator, $value);
        });
    }

    /**
     * Add an "or where" clause to a relationship query.
     *
     * @param  string  $relation
     * @param  \Closure|string|array|\Illuminate\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function orWhereRelation($relation, $column, $operator = null, $value = null)
    {
        return $this->orWhereHas($relation, function ($query) use ($column, $operator, $value) {
            $query->where($column, $operator, $value);
        });
    }
}
