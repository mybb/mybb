<?php

namespace MyBB\Http\Controllers\Admin;

use Illuminate\Http\Response;

class ToolsController extends AbstractController
{
    /**
     * Show the user the PHP Info page, wrapped inside the ACP layout container.
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @return Response
     */
    public function getPhpInfo()
    {
        $this->logAdminAction();

        ob_start();
        phpinfo();
        $phpInfo = ob_get_contents();
        ob_get_clean();

        return $this->viewResponse('admin/tools/php_info.twig', [
            'php_info' => $phpInfo,
        ]);
    }
}