/**
 * CPTSA Driving School — API Client
 * Drop-in replacement for the old localStorage-based calls.
 * Include this file in every HTML page:
 *   <script src="api_client.js"></script>
 *
 * All functions return Promises.
 * Set window.API_BASE before loading if your PHP files live in a subfolder,
 * e.g.  window.API_BASE = '/cptsa_backend/api';
 */

(function (global) {
    'use strict';

    // Auto-detect base path — works whether the project is at root or in a subfolder
    // e.g.  http://localhost/cptsa/  ->  BASE = '/cptsa/api'
    //       http://localhost/        ->  BASE = '/api'
    // You can override by setting:  window.API_BASE = '/my/path/api';  before this script loads.
    const _autoBase = (function() {
        var scripts = document.querySelectorAll('script[src]');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src; // resolved absolute URL, not the raw attribute
            if (src && src.indexOf('api_client.js') !== -1) {
                // strip filename, append 'api'
                return src.replace(/api_client\.js.*$/, '').replace(/\/$/, '') + '/api';
            }
        }
        return 'api';
    })();
    const BASE = (global.API_BASE || _autoBase).replace(/\/$/, '');

    // ── Core fetch helper ─────────────────────────────────
    async function _req(endpoint, params = {}, method = 'GET', body = null) {
        const qs = new URLSearchParams(params).toString();
        const url = `${BASE}/${endpoint}${qs ? '?' + qs : ''}`;

        const init = {
            method,
            credentials: 'include',   // send session cookie
            headers: {},
        };

        if (body !== null) {
            if (body instanceof FormData) {
                init.body = body;       // multipart — don't set Content-Type
            } else {
                init.headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(body);
            }
        }

        const res = await fetch(url, init);
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Request failed');
        return json.data;
    }

    const get  = (ep, p)    => _req(ep, p, 'GET');
    const post = (ep, p, b) => _req(ep, p, 'POST', b);
    const put  = (ep, p, b) => _req(ep, p, 'PUT',  b);
    const del  = (ep, p)    => _req(ep, p, 'DELETE');

    // ── AUTH ──────────────────────────────────────────────
    const Auth = {
        session:       ()    => get('auth.php', { action: 'session' }),
        adminLogin:    (u,p) => post('auth.php', { action: 'admin_login' },   { username: u, password: p }),
        studentLogin:  (id,pw)=> post('auth.php', { action: 'student_login' }, { id, password: pw }),
        logout:        ()    => post('auth.php', { action: 'logout' }),
    };

    // ── STUDENTS ──────────────────────────────────────────
    const Students = {
        list:           (search = '') => get('students.php',  { action: 'list', search }),
        get:            (id)          => get('students.php',  { action: 'get',  id }),
        create:         (data)        => post('students.php', { action: 'create' }, data),
        update:         (id, data)    => put('students.php',  { action: 'update', id }, data),
        delete:         (id)          => del('students.php',  { action: 'delete', id }),
        toggleStatus:   (id)          => post('students.php', { action: 'toggle_status', id }),
        payRestart:     (id, vehicle) => post('students.php', { action: 'pay_restart', id, vehicle }),
        changePassword: (data)        => post('students.php', { action: 'change_password' }, data),
        dashboard:      ()            => get('students.php',  { action: 'dashboard' }),
        hours:          (id)          => get('students.php',  { action: 'hours', id }),

        /** Upload profile photo — pass a File object */
        uploadPhoto: (file) => {
            const fd = new FormData();
            fd.append('photo', file);
            return _req('students.php', { action: 'upload_photo' }, 'POST', fd);
        },
    };

    // ── TIMETABLE ─────────────────────────────────────────
    const Timetable = {
        slots:       (vehicle = '', date = '') => get('timetable.php', { action: 'slots', vehicle, date }),
        createSlot:  (data)    => post('timetable.php', { action: 'create_slot' }, data),
        deleteSlot:  (id)      => del('timetable.php',  { action: 'delete_slot', id }),
        breakdown:   (vehicle = '', date = '') => get('timetable.php', { action: 'breakdown', vehicle, date }),
        book:        (slot_id) => post('timetable.php', { action: 'book' }, { slot_id }),
        cancel:      (slot_id) => del('timetable.php',  { action: 'cancel', slot_id }),
        myBookings:  ()        => get('timetable.php',  { action: 'my_bookings' }),
    };

    // ── ANNOUNCEMENTS ─────────────────────────────────────
    const Announcements = {
        list:   ()      => get('announcements.php', { action: 'list' }),
        create: (data)  => post('announcements.php', { action: 'create' }, data),
        delete: (id)    => del('announcements.php',  { action: 'delete', id }),
    };

    // ── DOCUMENTS ─────────────────────────────────────────
    const Documents = {
        list:     ()     => get('documents.php', { action: 'list' }),
        delete:   (id)   => del('documents.php', { action: 'delete', id }),
        downloadUrl: (id) => `${BASE}/documents.php?action=download&id=${id}`,

        /** Upload a file — pass a File object */
        upload: (file) => {
            const fd = new FormData();
            fd.append('file', file);
            return _req('documents.php', { action: 'upload' }, 'POST', fd);
        },
    };

    // ── FEEDBACK ──────────────────────────────────────────
    const Feedback = {
        list:    ()     => get('feedback.php',  { action: 'list' }),
        summary: ()     => get('feedback.php',  { action: 'summary' }),
        submit:  (data) => post('feedback.php', { action: 'submit' }, data),
        delete:  (id)   => del('feedback.php',  { action: 'delete', id }),
    };

    // ── MESSAGES ──────────────────────────────────────────
    const Messages = {
        send:   (data) => post('messages.php', { action: 'send' }, data),
        inbox:  ()     => get('messages.php',  { action: 'inbox' }),
        list:   ()     => get('messages.php',  { action: 'list' }),
        delete: (id)   => del('messages.php',  { action: 'delete', id }),
    };

    // ── EXAMS ─────────────────────────────────────────────
    const Exams = {
        list:     (search = '') => get('exams.php',  { action: 'list', search }),
        myExams:  ()            => get('exams.php',  { action: 'my_exams' }),
        update:   (data)        => post('exams.php', { action: 'update' }, data),
    };

    // ── VEHICLES ──────────────────────────────────────────
    const Vehicles = {
        list:   ()     => get('vehicles.php',  { action: 'list' }),
        create: (data) => post('vehicles.php', { action: 'create' }, data),
        delete: (id)   => del('vehicles.php',  { action: 'delete', id }),
    };

    // ── INSTRUCTORS ───────────────────────────────────────
    const Instructors = {
        list:   ()       => get('instructors.php',  { action: 'list' }),
        delete: (id)     => del('instructors.php',  { action: 'delete', id }),

        create: (data, photoFile) => {
            if (photoFile) {
                const fd = new FormData();
                Object.entries(data).forEach(([k, v]) => {
                    if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x));
                    else fd.append(k, v);
                });
                fd.append('photo', photoFile);
                return _req('instructors.php', { action: 'create' }, 'POST', fd);
            }
            return post('instructors.php', { action: 'create' }, data);
        },

        update: (id, data, photoFile) => {
            if (photoFile) {
                const fd = new FormData();
                Object.entries(data).forEach(([k, v]) => {
                    if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x));
                    else fd.append(k, v);
                });
                fd.append('photo', photoFile);
                return _req('instructors.php', { action: 'update', id }, 'PUT', fd);
            }
            return put('instructors.php', { action: 'update', id }, data);
        },
    };

    // ── FEES ──────────────────────────────────────────────
    const Fees = {
        list:   ()     => get('fees.php',  { action: 'list' }),
        create: (data) => post('fees.php', { action: 'create' }, data),
        delete: (id)   => del('fees.php',  { action: 'delete', id }),
    };

    // ── SETTINGS / FORMS ──────────────────────────────────
    const Settings = {
        getRegCode:  ()     => get('settings.php',  { action: 'reg_code' }),
        setRegCode:  (code) => post('settings.php', { action: 'set_reg_code' }, { code }),
        stats:       ()     => get('settings.php',  { action: 'stats' }),

        signinLog:   (name, phone, reg_code) =>
            post('settings.php', { action: 'signin_log' }, { name, phone, reg_code }),
        signinLogs:  ()     => get('settings.php',   { action: 'signin_logs' }),
        deleteLog:   (id)   => del('settings.php',   { action: 'delete_log', id }),

        formSubmit:  (data) => post('settings.php', { action: 'form_submit' }, data),
        formGet:     (p)    => get('settings.php',  { action: 'form_get', ...p }),
        formList:    ()     => get('settings.php',  { action: 'form_list' }),
        formDelete:  (id)   => del('settings.php',  { action: 'form_delete', id }),
    };

    // ── Expose as global CPTSA namespace ──────────────────
    global.CPTSA = {
        Auth, Students, Timetable, Announcements, Documents,
        Feedback, Messages, Exams, Vehicles, Instructors, Fees, Settings,
    };

})(window);