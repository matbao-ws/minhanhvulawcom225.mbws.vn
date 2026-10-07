<?php

namespace Extensions\Example\Http\Controllers;

use Extensions\Example\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NoteController extends Controller
{
    public function index()
    {
        return view('example::notes.index', [
            'notes' => Note::query()->with('author')->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        Note::query()->create(['body' => $data['body'], 'user_id' => $request->user()->id]);

        return redirect()->route('ext.example.notes.index')->with('success', __('example::notes.saved'));
    }
}
