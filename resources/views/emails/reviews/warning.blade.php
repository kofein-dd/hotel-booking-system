@component('mail::message')
    # Предупреждение о нарушении правил отзывов

    Уважаемый(ая) {{ $review->user->name }},

    Мы обнаружили нарушение правил публикации отзывов в вашем отзыве.

    ## Информация о вашем отзыве:
    - **Отель:** {{ $review->hotel->name }}
    - **Рейтинг:** {{ $review->rating }}/5
    - **Дата публикации:** {{ $review->created_at->format('d.m.Y H:i') }}

    ## Краткое содержание отзыва:
    {{ Str::limit($review->comment, 200) }}

    @if($report)
        ## Причина жалобы:
        - **Тип нарушения:** {{ $report->report_type }}
        - **Комментарий:** {{ $report->report_comment }}
    @endif

    @if($adminMessage)
        ## Сообщение от администратора:
        {{ $adminMessage }}
    @endif

    ## Возможные последствия:
    - Ваш отзыв может быть скрыт или удален
    - При повторных нарушениях возможна блокировка аккаунта

    @component('mail::button', ['url' => $reviewUrl])
        Просмотреть свой отзыв
    @endcomponent

    Если вы считаете, что это ошибка, пожалуйста, свяжитесь с [поддержкой]({{ $supportUrl }}).

    С уважением,
    Команда модерации {{ config('app.name') }}

    @component('mail::subcopy')
        [Правила публикации отзывов]({{ $rulesUrl }})
    @endcomponent
@endcomponent
