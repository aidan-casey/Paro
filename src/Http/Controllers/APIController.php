<?php

namespace AidanCasey\Para\Http\Controllers;

use JsonSerializable;
use InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Optimus\Architect\Architect;
use Illuminate\Http\Request;

abstract class APIController extends Controller
{
    /**
     * Default values for our query parameters can be set here
     * @var array
     */
    protected $defaults = [];

    /***************************************************************************
    * Utility Methods
    ***************************************************************************/

    /**
     * Takes the HTTP parameters and formats them so they can be applied
     * to the query.
     *
     * @return array
     */
    protected function parseResourceOptions($request = null)
    {
        if ($request === null) {
            $request = request();
        }

        // This ensures that we always have the neccessary keys set,
        // just in case the developer decides to customize them
        $this->defaults = array_merge(
            [
                'fields'        => [],
                'filters'       => [],
                'includes'      => [],
                'includeMode'   => 'embed',
                'limit'         => null,
                'page'          => null,
                'sort'          => [],
                'start'         => null,
            ],
            $this->defaults
        );

        // Here we are decoding any parameters that are JSON formatted
        $fields = json_decode(
            $request->query(
                Config::get('paro.parameters.fields'),
                $this->defaults['fields']
            ),
            true
        );

        $rawFilters = json_decode(
            $request->query(
                Config::get('paro.parameters.filters'),
                $this->defaults['filters']
            ),
            true
        );
        $filters = $this->parseFilters($rawFilters);
        
        $rawIncludes = json_decode(
            $request->query(
                Config::get('paro.parameters.includes'),
                $this->defaults['includes']
            ),
            true
        );
        $includes = $this->parseIncludes($rawIncludes);

        $rawSort = json_decode(
            $request->query(
                Config::get('paro.parameters.sort'),
                $this->defaults['sort']
            ),
            true
        );
        $sort = $this->parseSort($rawSort);

        // These are our non-JSON formatted parameters
        $limit  = $request->query(
            Config::get('paro.parameters.limit'),
            $this->defaults['limit']
        );
        $page   = $request->query(
            Config::get('paro.parameters.page'),
            $this->defaults['page']
        );
        $start  = $request->query(
            Config::get('paro.parameters.start'),
            $this->defaults['start']
        );

        if ($page !== null && $limit === null) {
            throw new InvalidArgumentException('Cannot use page option without the limit option!');
        }

        return [
            'fields'        => $fields,
            'filters'       => $filters,
            'includes'      => $includes['resources'],
            'includeModes'  => $includes['modes'],
            'limit'         => $limit,
            'page'          => $page,
            'sort'          => $sort,
            'start'         => $start,
        ];
    }

    /**
     * Parse the filter array into filters.
     * Filters are formatted as key:operator(value)
     * Example: name:eq(esben)
     *
     * @param  array  $filter_groups
     *
     * @return array
     */
    protected function parseFilters(array $filter_groups)
    {
        $return = [];

        foreach($filter_groups as $group) {
            if (!array_key_exists('filters', $group)) {
                throw new InvalidArgumentException(
                    'Filter parameter does not have the \'filters\' key.'
                );
            }

            $filters = array_map(
                function ($filter) {
                    if (!isset($filter['not'])) {
                        $filter['not'] = false;
                    }

                    return $filter;
                },
                $group['filters']
            );

            $return[] = [
                'filters' => $filters,
                'or' => isset($group['or']) ? $group['or'] : false
            ];
        }

        return $return;
    }

    /**
     * Parse the include array into resource, columns, and modes
     *
     * @param  array  $includes
     *
     * @return array The parsed resources and their respective modes
     */
    protected function parseIncludes(array $includes)
    {
        $return = [
            'resources'     => [],
            'includeModes'  => [],
        ];

        foreach ($includes as $include) {
            if (! isset($include['resource'])) {
                throw new InvalidArgumentException('Invalid resource in includes array!');
            }

            if (! isset($include['columns'])) {
                $include['columns'] = ['*'];
            }

            if (! isset($include['includeMode'])) {
                $include['includeMode'] = 'embed';
            }

            $return['resource'][] = $include['resource'] . ':' . implode($include['columns']);
            $return['includeModes'][$include['resource']] = $include['includeMode'];

            $return['resources'][] = $include;
            
            $explode = explode(':', $include);

            if (!isset($explode[1])) {
                $explode[1] = $this->defaults['mode'];
            }

            $return['resources'][] = $explode[0];
            $return['includeModes'][$explode[0]] = $explode[1];
        }

        return $return;
    }

    /**
     * Parse our sorting options.
     *
     * @param array $sort
     *
     * @return array
     */
    protected function parseSort(array $sort) {
        return array_map(
            function ($sort) {
                if (!isset($sort['direction'])) {
                    $sort['direction'] = 'asc';
                }

                return $sort;
            },
            $sort
        );
    }

    /**
     * Parse data using architect
     *
     * @param  mixed $data
     * @param  array  $options
     * @param  string $key
     *
     * @return mixed
     */
    protected function parseData($data, array $options, $key = null)
    {
        $architect = new Architect();

        return $architect->parseData($data, $options['includeModes'], $key);
    }

    /**
     * Create a json response.
     *
     * @param  mixed  $data
     * @param  integer $statusCode
     * @param  array  $headers
     *
     * @return Illuminate\Http\JsonResponse
     */
    protected function createJsonResponse($data, $statusCode = 200, array $headers = [])
    {
        if ($data instanceof Arrayable && !$data instanceof JsonSerializable) {
            $data = $data->toArray();
        }

        return new JsonResponse($data, $statusCode, $headers);
    }
}
