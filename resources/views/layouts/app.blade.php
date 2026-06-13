<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Audit Log</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.bootstrap5.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4 px-3">
    <span class="navbar-brand mb-0 h1">Audit Log</span>
</nav>

{{-- Component template — referenced by id from the Vue component definition below --}}
<script type="text/x-template" id="audit-log-tpl">
<div class="container-fluid">
    <div class="alert alert-info small">
        This log records all user actions in the system. Records are read-only.
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form v-on:submit.prevent="fetchRecords(1)">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">User</label>
                        <select ref="causerSelect" class="form-select form-select-sm">
                            <option value="">All users</option>
                            <option v-for="c in filters.causers" v-bind:key="c.id" v-bind:value="c.id">@{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Log Type</label>
                        <select v-model="search.log_name" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option v-for="n in filters.logNames" v-bind:key="n" v-bind:value="n">@{{ n }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Event</label>
                        <select v-model="search.event" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option v-for="e in filters.events" v-bind:key="e" v-bind:value="e">@{{ e }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Resource Type</label>
                        <select v-model="search.subject_type" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option v-for="t in filters.subjectTypes" v-bind:key="t.value" v-bind:value="t.value">@{{ t.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Resource ID</label>
                        <input v-model="search.subject_id" type="number" min="1" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Keyword</label>
                        <input v-model="search.q" type="text" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date From</label>
                        <input v-model="search.date_from" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date To</label>
                        <input v-model="search.date_to" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-sm btn-primary me-2">Search</button>
                        <button type="button" class="btn btn-sm btn-secondary" v-on:click="resetSearch">Reset</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div v-if="loading" class="text-center py-5 text-muted">Loading&hellip;</div>
            <div v-else class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Datetime</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Resource</th>
                            <th>Description</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(record, index) in records">
                            <tr v-bind:key="record.id">
                                <td class="text-muted small">@{{ offset + index + 1 }}</td>
                                <td>
                                    @{{ formatDate(record.created_at) }}
                                    <small class="text-muted d-block">@{{ formatTime(record.created_at) }}</small>
                                </td>
                                <td>
                                    @{{ causerName(record) }}
                                    <small class="text-muted d-block">@{{ causerRole(record) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary me-1">@{{ record.log_name }}</span>
                                    <span class="badge bg-info text-dark">@{{ record.event }}</span>
                                </td>
                                <td>@{{ subjectLabel(record) }}</td>
                                <td class="small">@{{ record.description }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-secondary py-0" v-on:click="toggle(record.id)">
                                        @{{ isExpanded(record.id) ? '▲' : '▼' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="isExpanded(record.id)" v-bind:key="'detail-' + record.id">
                                <td colspan="7" class="bg-light p-3">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <h6 class="fw-bold mb-2">Changes</h6>
                                            <table v-if="changeEntries(record).length" class="table table-sm table-bordered bg-white mb-0">
                                                <thead><tr><th>Field</th><th>Old</th><th>New</th></tr></thead>
                                                <tbody>
                                                    <tr v-for="entry in changeEntries(record)" v-bind:key="entry.key">
                                                        <td>@{{ entry.key }}</td>
                                                        <td class="text-danger">@{{ entry.old }}</td>
                                                        <td class="text-success">@{{ entry.new }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <p v-else class="text-muted small mb-0">No field changes recorded.</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-1">Actor</h6>
                                            <ul class="list-unstyled small mb-0">
                                                <li><span class="text-muted">User:</span> @{{ actor(record).name }}</li>
                                                <li><span class="text-muted">Role:</span> @{{ actor(record).role }}</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-1">Request</h6>
                                            <ul class="list-unstyled small mb-0">
                                                <li><span class="text-muted">Method:</span> @{{ req(record).method }}</li>
                                                <li><span class="text-muted">Route:</span> @{{ req(record).route }}</li>
                                                <li><span class="text-muted">URL:</span> @{{ req(record).url }}</li>
                                                <li><span class="text-muted">IP:</span> @{{ req(record).ip }}</li>
                                                <li><span class="text-muted">Agent:</span> @{{ req(record).user_agent }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!records.length">
                            <td colspan="7" class="text-center text-muted py-4">No activity records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer" v-if="pagination && pagination.last_page > 1">
            <nav class="d-flex align-items-center justify-content-between">
                <small class="text-muted">
                    Showing @{{ pagination.from }}–@{{ pagination.to }} of @{{ pagination.total }}
                </small>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" v-bind:class="{ disabled: pagination.current_page <= 1 }">
                        <button class="page-link" v-on:click="fetchRecords(pagination.current_page - 1)">&#8249;</button>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link">@{{ pagination.current_page }} / @{{ pagination.last_page }}</span>
                    </li>
                    <li class="page-item" v-bind:class="{ disabled: pagination.current_page >= pagination.last_page }">
                        <button class="page-link" v-on:click="fetchRecords(pagination.current_page + 1)">&#8250;</button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>
</script>

<div id="audit-log-app">
    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2.7.16/dist/vue.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
<script>
    window.axios = axios;
    var _csrf = document.querySelector('meta[name="csrf-token"]');
    if (_csrf) { axios.defaults.headers.common['X-CSRF-TOKEN'] = _csrf.content; }
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
</script>

@yield('script')

<script>
Vue.component('audit-log', {
    template: '#audit-log-tpl',
    data: function () {
        return {
            loading: false,
            records: [],
            pagination: null,
            expanded: {},
            search: this.defaultSearch(),
            filters: { causers: [], logNames: [], events: [], subjectTypes: [] },
        };
    },
    computed: {
        offset: function () {
            return this.pagination ? (this.pagination.current_page - 1) * this.pagination.per_page : 0;
        },
    },
    mounted: function () {
        if (window.activityLogFilters) {
            this.filters = Object.assign({}, this.filters, window.activityLogFilters);
        }
        var self = this;
        this.$nextTick(function () {
            self._causerTs = new TomSelect(self.$refs.causerSelect, {
                allowEmptyOption: true,
                onChange: function (value) { self.search.causer_id = value; },
            });
        });
        this.fetchRecords(1);
    },
    methods: {
        defaultSearch: function () {
            return { causer_id: '', log_name: '', event: '', subject_type: '', subject_id: '', q: '', date_from: '', date_to: '' };
        },
        resetSearch: function () {
            this.search = this.defaultSearch();
            if (this._causerTs) { this._causerTs.setValue('', true); }
            this.fetchRecords(1);
        },
        fetchRecords: function (page) {
            this.loading = true;
            var url = window.auditLogDataUrl || '/activity-logs/data';
            var params = Object.assign({}, this.search, { page: page || 1 });
            Object.keys(params).forEach(function (k) { if (!params[k]) delete params[k]; });
            var self = this;
            window.axios.get(url, { params: params })
                .then(function (res) {
                    self.records = res.data.data;
                    self.pagination = res.data;
                    self.expanded = {};
                })
                .catch(function (err) { console.error('AuditLog:', err); })
                .finally(function () { self.loading = false; });
        },
        toggle: function (id) {
            var next = Object.assign({}, this.expanded);
            next[id] = !next[id];
            this.expanded = next;
        },
        isExpanded: function (id) { return !!this.expanded[id]; },
        properties: function (record) { return record.properties || {}; },
        actor: function (record) { return this.properties(record).__actor || { name: '--', role: '--' }; },
        req: function (record) { return this.properties(record).__request || {}; },
        causerName: function (record) {
            var a = this.actor(record);
            return (a && a.name && a.name !== '--') ? a.name : (record.causer ? record.causer.name : '--');
        },
        causerRole: function (record) {
            var a = this.actor(record);
            return (a && a.role) ? a.role : '';
        },
        subjectLabel: function (record) {
            if (!record.subject_type) return '--';
            var base = record.subject_type.split('\\').pop();
            return record.subject_id ? base + ' #' + record.subject_id : base;
        },
        changeEntries: function (record) {
            var self = this;
            var props = this.properties(record);
            return Object.keys(props)
                .filter(function (k) { return k.indexOf('__') !== 0; })
                .map(function (k) {
                    var v = props[k];
                    if (v && typeof v === 'object' && 'new' in v) {
                        return { key: k, old: self.display(v.old), new: self.display(v.new) };
                    }
                    return { key: k, old: '', new: self.display(v) };
                });
        },
        display: function (value) {
            if (value === null || value === undefined) return '—';
            if (typeof value === 'object') return JSON.stringify(value);
            return value;
        },
        formatDate: function (dt) { return dt ? new Date(dt).toLocaleDateString() : ''; },
        formatTime: function (dt) { return dt ? new Date(dt).toLocaleTimeString() : ''; },
    },
});

new Vue({ el: '#audit-log-app' });
</script>

</body>
</html>
