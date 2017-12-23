<?php

namespace MyBB\Http\Controllers\Admin;

abstract class AbstractController extends \MyBB\Http\Controllers\AbstractController
{
    /**
     * Create a new instance of the controller.
     *
     * @param \Twig_Environment $twig A Twig environment used to render views.
     */
    public function __construct(\Twig_Environment $twig)
    {
        parent::__construct($twig);
    }

    protected function logAdminAction(array $data = [])
    {
        // TODO: log admin action
    }
}