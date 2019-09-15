<?php

namespace MyBB\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Twig\Environment;
use function MyBB\app;

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
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Create a response from the contents of a view.
     *
     * @param string $viewName The name of the view to send as a response.
     * @param array $context An array of variables to be passed to the view.
     *
     * @return Response A response with a HTTP 200 status code with the rendered view as its content.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function viewResponse($viewName, array $context = []) : Response
    {
        /**
         * @var \Twig\Environment $twig
         */
        $twig = app('twig');

        $rendered  = $twig->render($viewName, $context);

        return Response::create($rendered);
    }
}
