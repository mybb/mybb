<?php

namespace MyBB\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

abstract class AbstractController extends Controller
{
    /**
     * @var \Twig\Environment $twig
     */
    protected $twig;

    /**
     * Create a new instance of the controller.
     *
     * @param \Twig\Environment $twig A Twig environment used to render views.
     */
    public function __construct(\Twig\Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Create a response from the contents of a view.
     *
     * @param string $viewName The name of the view to send as a response.
     * @param array $context An array of variables to be passed to the view.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     *
     * @return Response A response with a HTTP 200 status code with the rendered view as its content.
     */
    protected function viewResponse($viewName, array $context = [])
    {
        /**
         * @var \Twig\Environment $twig
         */
        $twig = \MyBB\app('twig');

        $rendered  = $twig->render($viewName, $context);

        return Response::create($rendered);
    }
}
