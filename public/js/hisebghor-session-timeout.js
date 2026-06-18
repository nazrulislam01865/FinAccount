(function () {
    'use strict';

    var config = window.HISEBGHOR_SESSION || {};
    var timeoutMs = Number(config.timeoutMs || 0);
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');

    if (!timeoutMs || !config.timeoutUrl || !config.loginUrl || !csrfMeta) {
        return;
    }

    var csrfToken = csrfMeta.getAttribute('content') || '';
    var prefix = String(config.storagePrefix || 'hisebghor.session');
    var activityKey = prefix + '.lastActivityAt';
    var timeoutLockKey = prefix + '.timeoutLockAt';
    var lastActivityAt = Date.now();
    var lastHeartbeatAt = Date.now();
    var activityThrottleAt = 0;
    var timeoutTimer = null;
    var timedOut = false;

    function readSharedActivity() {
        try {
            var stored = Number(window.localStorage.getItem(activityKey));
            if (Number.isFinite(stored) && stored > 0) {
                return stored;
            }
        } catch (error) {
            // localStorage can be unavailable in private/restrictive browser modes.
        }

        return lastActivityAt;
    }

    function writeSharedActivity(value) {
        try {
            window.localStorage.setItem(activityKey, String(value));
        } catch (error) {
            // In-memory timer still works without localStorage.
        }
    }

    function redirectToLogin(url) {
        window.location.replace(url || config.loginUrl);
    }

    function scheduleTimeout() {
        window.clearTimeout(timeoutTimer);

        var sharedActivityAt = Math.max(lastActivityAt, readSharedActivity());
        var remaining = Math.max(0, timeoutMs - (Date.now() - sharedActivityAt));

        timeoutTimer = window.setTimeout(expireSession, remaining + 50);
    }

    function expireSession() {
        if (timedOut) {
            return;
        }

        var sharedActivityAt = Math.max(lastActivityAt, readSharedActivity());
        if ((Date.now() - sharedActivityAt) < timeoutMs) {
            scheduleTimeout();
            return;
        }

        timedOut = true;

        try {
            var existingLock = Number(window.localStorage.getItem(timeoutLockKey));
            if (Number.isFinite(existingLock) && (Date.now() - existingLock) < 10000) {
                redirectToLogin(config.loginUrl);
                return;
            }

            window.localStorage.setItem(timeoutLockKey, String(Date.now()));
            window.localStorage.removeItem(activityKey);
        } catch (error) {
            // Continue with server-side timeout request.
        }

        window.fetch(config.timeoutUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reason: 'inactivity' })
        }).then(function (response) {
            return response.json().catch(function () { return {}; });
        }).then(function (payload) {
            redirectToLogin(payload.redirect || config.loginUrl);
        }).catch(function () {
            redirectToLogin(config.loginUrl);
        });
    }

    function sendKeepAlive() {
        if (timedOut || !config.keepAliveUrl) {
            return;
        }

        var now = Date.now();
        if ((now - lastHeartbeatAt) < 60000) {
            return;
        }

        lastHeartbeatAt = now;

        window.fetch(config.keepAliveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ active: true })
        }).then(function (response) {
            if (response.redirected && response.url && response.url.indexOf(config.loginUrl) === 0) {
                timedOut = true;
                redirectToLogin(config.loginUrl);
                return;
            }

            if (response.status === 401 || response.status === 419) {
                timedOut = true;
                return response.json().catch(function () { return {}; }).then(function (payload) {
                    redirectToLogin(payload.redirect || config.loginUrl);
                });
            }

            if (response.ok) {
                lastHeartbeatAt = Date.now();
            }
        }).catch(function () {
            // Temporary network issues must not interrupt the active page.
        });
    }

    function registerActivity() {
        if (timedOut) {
            return;
        }

        var now = Date.now();
        var sharedActivityAt = Math.max(lastActivityAt, readSharedActivity());

        if ((now - sharedActivityAt) >= timeoutMs) {
            expireSession();
            return;
        }

        if ((now - activityThrottleAt) < 1000) {
            return;
        }

        activityThrottleAt = now;
        lastActivityAt = now;
        writeSharedActivity(now);
        scheduleTimeout();
        sendKeepAlive();
    }

    ['click', 'keydown', 'pointerdown', 'pointermove', 'touchstart'].forEach(function (eventName) {
        window.addEventListener(eventName, registerActivity, {
            passive: eventName !== 'keydown'
        });
    });

    document.addEventListener('scroll', registerActivity, {
        capture: true,
        passive: true
    });

    window.addEventListener('focus', registerActivity);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            registerActivity();
        }
    });

    window.addEventListener('pageshow', function () {
        if (!timedOut) {
            var sharedActivityAt = Math.max(lastActivityAt, readSharedActivity());
            if ((Date.now() - sharedActivityAt) >= timeoutMs) {
                expireSession();
            }
        }
    });

    window.addEventListener('storage', function (event) {
        if (event.key === activityKey && event.newValue) {
            var shared = Number(event.newValue);
            if (Number.isFinite(shared) && shared > lastActivityAt) {
                lastActivityAt = shared;
                scheduleTimeout();
            }
        }

        if (event.key === timeoutLockKey && event.newValue && !timedOut) {
            timedOut = true;
            redirectToLogin(config.loginUrl);
        }
    });

    try {
        window.localStorage.removeItem(timeoutLockKey);
    } catch (error) {
        // Ignore unavailable localStorage.
    }

    writeSharedActivity(lastActivityAt);
    scheduleTimeout();

    window.setInterval(function () {
        if (timedOut) {
            return;
        }

        var sharedActivityAt = Math.max(lastActivityAt, readSharedActivity());
        if ((Date.now() - sharedActivityAt) < 65000) {
            sendKeepAlive();
        }
    }, 30000);
}());
