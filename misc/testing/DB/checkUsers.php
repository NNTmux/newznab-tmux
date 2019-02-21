<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\User;

User::updateExpiredRoles();
User::deleteUnVerified();
