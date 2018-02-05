<?php

namespace App\Http\Controllers;

use App\Channel;
use App\Comment;
use App\Report;
use App\Submission;
use App\Traits\CachableChannel;
use Auth;
use Illuminate\Http\Request;
use App\Http\Resources\ReportedCommentResource;

class ReportCommentsController extends Controller
{
    use CachableChannel;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Stores a new report for a comment.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:comments,id', 
            'subject'    => 'required|string',
        ]);

        if (Comment::where('id', $request->id)->where('approved_at', '!=', null)->exists()) {
            return res(200, "The comment has already been approved by a moderator. That means it can not be reported.");
        }

        $report = Report::create([
            'subject'         => $request->subject,
            'reportable_type' => "App\Comment",
            'reportable_id'   => $request->id,
            'user_id'         => Auth::user()->id,
            'channel_id'      => Comment::where('id', $request->id)->value('channel_id'),
            'description'     => $request->description,
        ]);

        return res(201, 'Report submitted successfully.');
    }

    /**
     * Indexes the reported comments.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Support\Collection
     */
    public function index(Request $request)
    {
        $this->validate($request, [
            'channel_id'  => 'required|exists:channels,id',
            'type'     => 'nullable|in:solved,unsolved',
        ]);

        abort_unless($this->mustBeModerator($request->channel_id), 403);

        if ($request->type == 'solved') {
            return ReportedCommentResource::collection(
                Report::onlyTrashed()->whereHas('comment')->whereHas('reporter')->where([
                    'channel_id'      => $request->channel_id,
                    'reportable_type' => 'App\Comment',
                ])->with('reporter', 'comment.submission')->orderBy('created_at', 'desc')->simplePaginate(50)
            );
        }

        // default type which is "unsolved"
        return ReportedCommentResource::collection(
            Report::whereHas('comment')->whereHas('reporter')->where([
                'channel_id'      => $request->channel_id,
                'reportable_type' => 'App\Comment',
            ])->with('reporter', 'comment.submission')->orderBy('created_at', 'desc')->simplePaginate(50)
        );
    }
}
