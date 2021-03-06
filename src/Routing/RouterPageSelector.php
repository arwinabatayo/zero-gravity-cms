<?php

namespace ZeroGravity\Cms\Routing;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use ZeroGravity\Cms\Content\Page;

/**
 * Determine current page based on routing.
 */
class RouterPageSelector
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * RouterPageSelector constructor.
     *
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return null|Page
     */
    public function getCurrentPage(): ? Page
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        /* @var $route Route */
        $params = $request->attributes->get('_route_params');
        if (isset($params['page']) && $params['page'] instanceof Page) {
            return $params['page'];
        }

        return null;
    }
}
