<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Illuminate\Http\Request;

class AdminPageController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->adminBasePage();
    }
}
