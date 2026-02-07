@component('mail::message')
    # Тестовое уведомление

    **Тип уведомления:** {{ $notificationType }}

    @if($customMessage)
        **Сообщение:**
        {{ $customMessage }}
    @endif

    ## Системная информация:
    - **Время отправки:** {{ $timestamp }}
    - **Система:** {{ $systemInfo['app_name'] }}
    - **Окружение:** {{ $systemInfo['env'] }}
    - **URL:** {{ $systemInfo['url'] }}

    @if(!empty($testData))
        ## Тестовые данные:
        @foreach($testData as $key => $value)
            - **{{ $key }}:** {{ is_array($value) ? json_encode($value) : $value }}
        @endforeach
    @endif

    Это тестовое сообщение отправлено для проверки работы системы уведомлений.

    @component('mail::button', ['url' => url('/')])
        Перейти на сайт
    @endcomponent

@endcomponent
