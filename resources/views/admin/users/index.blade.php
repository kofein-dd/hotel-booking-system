@extends('admin.layouts.app')

@section('title', 'Пользователи')
@section('page-title', 'Управление пользователями')

@section('content')
    @include('admin.components.alert')

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Список пользователей</h5>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Добавить пользователя
            </a>
        </div>
        <div class="card-body">
            <!-- Фильтры -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <input type="text" class="form-control" placeholder="Поиск по имени или email..."
                           id="searchInput">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="roleFilter">
                        <option value="">Все роли</option>
                        <option value="admin">Администраторы</option>
                        <option value="moderator">Модераторы</option>
                        <option value="user">Пользователи</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">Все статусы</option>
                        <option value="active">Активные</option>
                        <option value="inactive">Неактивные</option>
                        <option value="banned">Заблокированные</option>
                    </select>
                </div>
            </div>

            <!-- Таблица пользователей -->
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Телефон</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($user->avatar)
                                        <img src="{{ asset($user->avatar) }}" alt="{{ $user->name }}"
                                             class="rounded-circle me-2" width="32" height="32">
                                    @else
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2"
                                             style="width: 32px; height: 32px;">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                    @endif
                                    {{ $user->name }}
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone ?? 'Не указан' }}</td>
                            <td>
                                @php
                                    $roleColors = [
                                        'admin' => 'danger',
                                        'moderator' => 'warning',
                                        'user' => 'success',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $roleColors[$user->role] ?? 'secondary' }}">
                                @if($user->role == 'admin') Администратор
                                    @elseif($user->role == 'moderator') Модератор
                                    @else Пользователь
                                    @endif
                            </span>
                            </td>
                            <td>
                                @if($user->isBanned())
                                    <span class="badge bg-danger">Заблокирован</span>
                                @else
                                    <span class="badge bg-{{ $user->status == 'active' ? 'success' : 'secondary' }}">
                                    {{ $user->status == 'active' ? 'Активен' : 'Неактивен' }}
                                </span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->format('d.m.Y') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="btn btn-outline-primary" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="btn btn-outline-warning" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    @if($user->isBanned())
                                        <form action="{{ route('admin.users.unban', $user) }}"
                                              method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success"
                                                    title="Разблокировать"
                                                    onclick="return confirm('Разблокировать пользователя?')">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.users.ban', $user) }}"
                                              method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger"
                                                    title="Заблокировать"
                                                    onclick="return confirm('Заблокировать пользователя?')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <form action="{{ route('admin.users.destroy', $user) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Удалить пользователя? Это действие нельзя отменить.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Пользователи не найдены</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            @if($users->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Поиск
            const searchInput = document.getElementById('searchInput');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            const tableRows = document.querySelectorAll('#usersTable tbody tr');

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();
                const roleValue = roleFilter.value;
                const statusValue = statusFilter.value;

                tableRows.forEach(row => {
                    const name = row.cells[1].textContent.toLowerCase();
                    const email = row.cells[2].textContent.toLowerCase();
                    const role = row.cells[4].textContent.toLowerCase();
                    const status = row.cells[5].textContent.toLowerCase();

                    let showRow = true;

                    // Поиск по имени и email
                    if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm)) {
                        showRow = false;
                    }

                    // Фильтр по роли
                    if (roleValue) {
                        const roleText = role.includes('админ') ? 'admin' :
                            role.includes('модератор') ? 'moderator' : 'user';
                        if (roleText !== roleValue) {
                            showRow = false;
                        }
                    }

                    // Фильтр по статусу
                    if (statusValue) {
                        const statusText = status.includes('активен') ? 'active' :
                            status.includes('неактивен') ? 'inactive' :
                                status.includes('заблокирован') ? 'banned' : '';
                        if (statusText !== statusValue) {
                            showRow = false;
                        }
                    }

                    row.style.display = showRow ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', filterTable);
            roleFilter.addEventListener('change', filterTable);
            statusFilter.addEventListener('change', filterTable);
        });
    </script>
@endpush
