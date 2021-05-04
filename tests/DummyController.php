<?php

namespace TuneZilla\OpenAPITestValidation\Test;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DummyController extends Controller
{
    public function index(Request $request)
    {
        $data = ['foo' => 'bar'];
        $code = 200;

        if ($request->input('bad_enum')) {
            $data['foo'] = 'foobar';
        }

        if ($request->input('bad_code')) {
            $code = 418;
        }

        return response()->json($data, $code);
    }
}
