<?php

namespace JutForm\Core;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): void;
}
