<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuggestedQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FAQSuggestionController extends Controller
{
    public function index()
    {
        Gate::authorize('view-faq-suggestions');

        $suggestions = SuggestedQuestion::with(['user', 'reviewer', 'faq'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.faq.suggestions.index', compact('suggestions'));
    }

    public function show(SuggestedQuestion $suggestion)
    {
        Gate::authorize('view-faq-suggestions');

        $suggestion->load(['user', 'reviewer', 'faq']);

        return view('admin.faq.suggestions.show', compact('suggestion'));
    }

    public function approve(SuggestedQuestion $suggestion)
    {
        Gate::authorize('manage-faq-suggestions');

        $suggestion->updateStatus(SuggestedQuestion::STATUS_APPROVED, auth()->user());

        return redirect()->route('admin.faq.suggestions.index')
            ->with('success', 'Вопрос одобрен');
    }

    public function reject(SuggestedQuestion $suggestion, Request $request)
    {
        Gate::authorize('manage-faq-suggestions');

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $suggestion->updateStatus(SuggestedQuestion::STATUS_REJECTED, auth()->user(), $request->reason);

        return redirect()->route('admin.faq.suggestions.index')
            ->with('success', 'Вопрос отклонен');
    }

    public function addToFaq(SuggestedQuestion $suggestion, Request $request)
    {
        Gate::authorize('manage-faq-suggestions');

        $request->validate([
            'answer' => 'required|string|min:10',
            'category' => 'required|string|max:100',
        ]);

        // Создаем FAQ из предложения
        $faq = \App\Models\FAQ::create([
            'question' => $suggestion->question,
            'answer' => $request->answer,
            'category' => $request->category,
            'order' => 0,
            'is_active' => true,
        ]);

        $suggestion->update([
            'status' => SuggestedQuestion::STATUS_ADDED_TO_FAQ,
            'faq_id' => $faq->id,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.faq.suggestions.index')
            ->with('success', 'Вопрос добавлен в FAQ');
    }
}
