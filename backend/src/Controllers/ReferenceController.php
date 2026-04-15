<?php

namespace JutForm\Controllers;

use JutForm\Core\Request;
use JutForm\Core\Response;
use JutForm\Models\CountryRepository;
use JutForm\Models\FieldTypeRepository;

class ReferenceController
{
    public function fieldTypes(Request $request): void
    {
        Response::json(['field_types' => FieldTypeRepository::all()]);
    }

    public function countries(Request $request): void
    {
        Response::json(['countries' => CountryRepository::all()]);
    }
}
