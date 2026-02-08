<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Левая часть навбара -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">Главная</a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="{{ route('home') }}" target="_blank" class="nav-link">
                <i class="fas fa-external-link-alt"></i> На сайт
            </a>
        </li>
    </ul>

    <!-- Правая часть навбара -->
    <ul class="navbar-nav ml-auto">
        <!-- Уведомления -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-bell"></i>
                @if($unreadNotificationsCount = auth()->user()->unreadNotifications->count())
                    <span class="badge badge-warning navbar-badge">{{ $unreadNotificationsCount }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-header">{{ $unreadNotificationsCount }} новых уведомлений</span>
                <div class="dropdown-divider"></div>
                @foreach(auth()->user()->notifications->take(5) as $notification)
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-envelope mr-2"></i> {{ Str::limit($notification->data['message'] ?? 'Новое уведомление', 30) }}
                        <span class="float-right text-muted text-sm">{{ $notification->created_at->diffForHumans() }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                @endforeach
                <a href="{{ route('admin.notifications.index') }}" class="dropdown-item dropdown-footer">Все уведомления</a>
            </div>
        </li>

        <!-- Сообщения чата -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-comments"></i>
                @if($unreadMessagesCount = \App\Models\ChatMessage::where('read_at', null)->where('is_admin_message', false)->count())
                    <span class="badge badge-danger navbar-badge">{{ $unreadMessagesCount }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-header">{{ $unreadMessagesCount }} непрочитанных сообщений</span>
                <div class="dropdown-divider"></div>
                @foreach(\App\Models\ChatMessage::with('user')->where('read_at', null)->where('is_admin_message', false)->latest()->take(5)->get() as $message)
                    <a href="{{ route('admin.chat.index', ['user_id' => $message->user_id]) }}" class="dropdown-item">
                        <div class="media">
                            <img src="{{ $message->user->avatar_url ?? asset('img/default-avatar.png') }}"
                                 alt="User Avatar" class="img-size-50 mr-3 img-circle">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    {{ $message->user->name }}
                                    <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">{{ Str::limit($message->message, 40) }}</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> {{ $message->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                @endforeach
                <a href="{{ route('admin.chat.index') }}" class="dropdown-item dropdown-footer">Все сообщения</a>
            </div>
        </li>

        <!-- Пользовательское меню -->
        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <img src="{{ auth()->user()->avatar_url ?? asset('img/default-avatar.png') }}"
                     class="user-image img-circle elevation-2" alt="User Image">
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <!-- Заголовок пользователя -->
                <li class="user-header bg-primary">
                    <img src="{{ auth()->user()->avatar_url ?? asset('img/default-avatar.png') }}"
                         class="img-circle elevation-2" alt="User Image">
                    <p>
                        {{ auth()->user()->name }}
                        <small>Администратор</small>
                    </p>
                </li>
                <!-- Меню тела -->
                <li class="user-body">
                    <div class="row">
                        <div class="col-4 text-center">
                            <a href="{{ route('profile.index') }}">Профиль</a>
                        </div>
                        <div class="col-4 text-center">
                            <a href="{{ route('admin.settings.index') }}">Настройки</a>
                        </div>
                    </div>
                </li>
                <!-- Меню футера -->
                <li class="user-footer">
                    <a href="{{ route('profile.edit') }}" class="btn btn-default btn-flat">
                        <i class="fas fa-user-cog"></i> Профиль
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-default btn-flat float-right">
                            <i class="fas fa-sign-out-alt"></i> Выйти
                        </button>
                    </form>
                </li>
            </ul>
        </li>

        <!-- Кнопка полноэкранного режима -->
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
    </ul>
</nav>
