<?php

namespace Oxycoder\ApiDoc\Generators;

use Exception;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Oxycoder\ApiDoc\Reflection\DocBlock\Tag;
use League\Fractal\Resource\Collection;
use ReflectionClass;

class DingoGenerator extends AbstractGenerator
{
    /**
     * @param \Illuminate\Routing\Route $route
     * @param array $bindings
     * @param array $headers
     * @param bool $withResponse
     *
     * @return array
     */


    public function processRoute($route, $bindings = [], $headers = [], $withResponse = true)
     {
        $content = '';

        $routeAction = $route->getAction();
        $routeGroup = $this->getRouteGroup($routeAction['uses']);
        $routeDescription = $this->getRouteDescription($routeAction['uses']);
        $showresponse = null;

        if ($withResponse) {
            $response = null;
            $docblockResponse = $this->getDocblockResponse($routeDescription['tags']);

            if ($docblockResponse) {
                // we have a response from the docblock ( @response )
                $response = json_decode($docblockResponse->getContent());
                $showresponse = true;
            }
            if (! $response) {
                $transformerResponse = $this->getTransformerResponse($routeDescription['tags']);
                if ($transformerResponse) {
                    // we have a transformer response from the docblock ( @transformer || @transformercollection )
                    $response = json_decode($transformerResponse->getContent());
                    $showresponse = true;
                }
            }
            if (! $response) {
                try {
                    $response = $this->getRouteResponse($route, $bindings, $headers);
                    if (is_object($response)) {
                        $response = json_encode(json_decode($response->getContent(), JSON_PRETTY_PRINT));
                    }
                } catch (Exception $e) {
                }
            }else{
                if (is_object($response)) {
                    $response = json_decode(json_encode($response),JSON_PRETTY_PRINT);
                }else{
                    //Formart explicietly defined response in the docblock
                    $response = json_decode( $response, JSON_PRETTY_PRINT); 
                }
            }
            $content = $response;
        }

        return $this->getParameters([
            'id' => md5($route->uri().':'.implode($route->getMethods())),
            'resource' => $routeGroup,
            'title' => $routeDescription['short'],
            'description' => $routeDescription['long'],
            'methods' => $route->getMethods(),
            'uri' => $route->uri(),
            'parameters' => [],
            'response' => $content,
            'showresponse' => $showresponse,
        ], $routeAction, $bindings);
    }


     /**
     * Get a response from the transformer tags.
     *
     * @param array $tags
     *
     * @return mixed
     */
    protected function getTransformerResponse($tags)
    {
        try {
            $transFormerTags = array_filter($tags, function ($tag) {
                if (! ($tag instanceof Tag)) {
                    return false;
                }

                return \in_array(\strtolower($tag->getName()), ['transformer', 'transformercollection']);
            });
            if (empty($transFormerTags)) {
                // we didn't have any of the tags so goodbye
                return false;
            }

            $modelTag = array_first(array_filter($tags, function ($tag) {
                if (! ($tag instanceof Tag)) {
                    return false;
                }

                return \in_array(\strtolower($tag->getName()), ['transformermodel']);
            }));
            $tag = \array_first($transFormerTags);
            $transformer = $tag->getContent();
            if (! \class_exists($transformer)) {
                // if we can't find the transformer we can't generate a response
                return;
            }
            $demoData = [];

            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('transform');
            $parameter = \array_first($method->getParameters());
            $type = null;
            if ($modelTag) {
                $type = $modelTag->getContent();
            }
            if (version_compare(PHP_VERSION, '7.0.0') >= 0 && \is_null($type)) {
                // we can only get the type with reflection for PHP 7
                if ($parameter->hasType() &&
                ! $parameter->getType()->isBuiltin() &&
                \class_exists((string) $parameter->getType())) {
                    //we have a type
                    $type = (string) $parameter->getType();
                }
            }
            if ($type) {
                // we have a class so we try to create an instance
                $demoData = new $type;
                try {
                    // try a factory
                    $demoData = \factory($type)->make();
                } catch (\Exception $e) {
                    if ($demoData instanceof \Illuminate\Database\Eloquent\Model) {
                        // we can't use a factory but can try to get one from the database
                        try {
                            // check if we can find one
                            $newDemoData = $type::first();
                            if ($newDemoData) {
                                $demoData = $newDemoData;
                            }
                        } catch (\Exception $e) {
                            // do nothing
                        }
                    }
                }
            }

            $fractal = new Manager();
            $resource = [];
            if ($tag->getName() == 'transformer') {
                // just one
                $resource = new Item($demoData, new $transformer);
            }
            if ($tag->getName() == 'transformercollection') {
                // a collection
                $resource = new Collection([$demoData, $demoData], new $transformer);
            }

            return \response($fractal->createData($resource)->toJson());
        } catch (\Exception $e) {
            // it isn't possible to parse the transformer
            return;
        }
    }



    /**
     * Prepares / Disables route middlewares.
     *
     * @param  bool $disable
     *
     * @return  void
     */
    public function prepareMiddleware($disable = true)
    {
        // Not needed by Dingo
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $dispatcher = app('Dingo\Api\Dispatcher')->raw();

        collect($server)->map(function ($key, $value) use ($dispatcher) {
            $dispatcher->header($value, $key);
        });

        return call_user_func_array([$dispatcher, strtolower($method)], [$uri]);
    }

    /**
     * {@inheritdoc}
     */
    public function getUri($route)
    {
        return $route->uri();
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods($route)
    {
        return $route->getMethods();
    }
}
