class PushNotifications {
    constructor() {
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.subscription = null;
        this.publicKey = null;
    }

    /**
     * Инициализация push-уведомлений
     */
    async init() {
        if (!this.isSupported) {
            console.warn('Push notifications are not supported in this browser');
            return false;
        }

        try {
            // Регистрируем Service Worker
            const registration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registered:', registration);

            // Запрашиваем разрешение на уведомления
            const permission = await Notification.requestPermission();

            if (permission === 'granted') {
                // Получаем VAPID ключ с сервера
                await this.fetchPublicKey();

                // Получаем существующую подписку
                this.subscription = await registration.pushManager.getSubscription();

                if (!this.subscription) {
                    // Создаем новую подписку
                    this.subscription = await this.subscribe(registration);
                } else {
                    // Обновляем существующую подписку
                    await this.updateSubscription();
                }

                return true;
            } else {
                console.warn('Push notification permission denied');
                return false;
            }
        } catch (error) {
            console.error('Error initializing push notifications:', error);
            return false;
        }
    }

    /**
     * Запросить VAPID public key с сервера
     */
    async fetchPublicKey() {
        try {
            const response = await fetch('/api/push/vapid-public-key');
            const data = await response.json();
            this.publicKey = data.publicKey;
        } catch (error) {
            console.error('Error fetching VAPID public key:', error);
        }
    }

    /**
     * Подписаться на push-уведомления
     */
    async subscribe(registration) {
        try {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.publicKey)
            });

            // Отправляем подписку на сервер
            await this.sendSubscriptionToServer(subscription);

            return subscription;
        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
            throw error;
        }
    }

    /**
     * Обновить подписку на сервере
     */
    async updateSubscription() {
        try {
            await this.sendSubscriptionToServer(this.subscription);
            console.log('Push subscription updated');
        } catch (error) {
            console.error('Error updating subscription:', error);
        }
    }

    /**
     * Отписаться от push-уведомлений
     */
    async unsubscribe() {
        if (!this.subscription) return;

        try {
            const unsubscribed = await this.subscription.unsubscribe();

            if (unsubscribed) {
                // Удаляем подписку с сервера
                await this.removeSubscriptionFromServer();
                this.subscription = null;
                console.log('Push notifications disabled');
                return true;
            }
        } catch (error) {
            console.error('Error unsubscribing from push notifications:', error);
        }

        return false;
    }

    /**
     * Отправить подписку на сервер
     */
    async sendSubscriptionToServer(subscription) {
        const response = await fetch('/api/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(subscription)
        });

        if (!response.ok) {
            throw new Error('Failed to send subscription to server');
        }

        console.log('Push subscription sent to server');
    }

    /**
     * Удалить подписку с сервера
     */
    async removeSubscriptionFromServer() {
        const response = await fetch('/api/push-subscriptions', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ endpoint: this.subscription.endpoint })
        });

        if (!response.ok) {
            throw new Error('Failed to remove subscription from server');
        }

        console.log('Push subscription removed from server');
    }

    /**
     * Конвертировать base64 строку в Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    /**
     * Проверить статус подписки
     */
    getSubscriptionStatus() {
        if (!this.isSupported) {
            return 'not_supported';
        }

        if (Notification.permission === 'denied') {
            return 'denied';
        }

        if (Notification.permission === 'granted' && this.subscription) {
            return 'subscribed';
        }

        return 'not_subscribed';
    }

    /**
     * Отправить тестовое уведомление
     */
    async sendTestNotification() {
        try {
            const response = await fetch('/api/push-subscriptions/test', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                console.log('Test notification sent');
                return true;
            }
        } catch (error) {
            console.error('Error sending test notification:', error);
        }

        return false;
    }
}

// Экспортируем глобально
window.PushNotifications = PushNotifications;

// Автоматическая инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    const pushNotifications = new PushNotifications();

    // Сохраняем в глобальной области видимости
    window.pushNotifications = pushNotifications;

    // Инициализируем, если пользователь авторизован
    if (window.user && window.user.id) {
        pushNotifications.init().then(initialized => {
            if (initialized) {
                console.log('Push notifications initialized successfully');
            }
        });
    }
});
