@component('mail::message')
    # Ваш вопрос получен

    Спасибо за ваш вопрос! Мы получили его и скоро рассмотрим.

    ## Информация о вашем вопросе:
    - **Вопрос:** {{ $question->question }}
    @if($question->description)
        - **Описание:** {{ $question->description }}
    @endif
    - **Код отслеживания:** {{ $trackingCode }}
    - **Дата отправки:** {{ $question->created_at->format('d.m.Y H:i') }}

    ## Что дальше?
    1. Наш модератор рассмотрит ваш вопрос
    2. Мы проверим, нет ли похожих вопросов в FAQ
    3. Вы получите уведомление о результате

    **Ориентировочное время рассмотрения:** {{ $estimatedReviewTime }}

    @component('mail::button', ['url' => $statusCheckUrl])
        Проверить статус вопроса
    @endcomponent

    @component('mail::button', ['url' => $faqUrl, 'color' => 'green'])
        Посмотреть FAQ
    @endcomponent

    Если у вас есть срочный вопрос, вы можете [связаться с поддержкой]({{ $contactUrl }}).

    С уважением,
    Команда поддержки {{ config('app.name') }}

    @component('mail::subcopy')
        Это автоматическое сообщение, пожалуйста, не отвечайте на него.
    @endcomponent
@endcomponent
