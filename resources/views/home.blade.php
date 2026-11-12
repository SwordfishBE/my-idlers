@section("title", "Dashboard")
<x-app-layout>
    <div class="dashboard-container">
        <!-- Stats Overview -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('servers.index') }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-value">{{ $information['servers'] }}</div>
                        <div class="stat-label">Servers</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('shared.index') }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-value">{{ $information['shared'] }}</div>
                        <div class="stat-label">Shared</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('reseller.index') }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-value">{{ $information['reseller'] }}</div>
                        <div class="stat-label">Reseller</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('domains.index') }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-value">{{ $information['domains'] }}</div>
                        <div class="stat-label">Domains</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('misc.index') }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-value">{{ $information['misc'] }}</div>
                        <div class="stat-label">Misc</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('dns.index') }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-value">{{ $information['dns'] }}</div>
                        <div class="stat-label">DNS</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Costs & Resources Row -->
        <div class="row g-3 mb-4">
            <!-- Costs Card -->
            <div class="col-12 col-lg-6">
                <div class="dashboard-card dashboard-card--overflow-visible">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">Costs</h5>
                        @php
                            $selectedCostFilterCount = count($information['selected_cost_filters']);
                            $initialCostFilterLabel = 'All services';

                            if ($selectedCostFilterCount === 1) {
                                $initialCostFilterLabel = $information['cost_filters'][$information['selected_cost_filters'][0]] ?? 'All services';
                            } elseif ($selectedCostFilterCount > 1 && $selectedCostFilterCount < count($information['cost_filters'])) {
                                $initialCostFilterLabel = $selectedCostFilterCount . ' selected';
                            }
                        @endphp
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label class="visually-hidden" for="costBillingCycleFilter">Billing cycle filter</label>
                            <select class="form-select form-select-sm w-auto" id="costBillingCycleFilter" aria-label="Filter costs by billing cycle">
                                @foreach($information['billing_cycle_filters'] as $billingCycle => $label)
                                    <option value="{{ $billingCycle }}" @selected($billingCycle === $information['selected_billing_cycle_filter'])>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="dropdown" data-bs-auto-close="outside">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="costFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span data-cost-filter-label>{{ $initialCostFilterLabel }}</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="costFilterDropdown" style="min-width: 15rem;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" value="all" id="cost-filter-all" @checked(count($information['selected_cost_filters']) === count($information['cost_filters']))>
                                        <label class="form-check-label fw-semibold" for="cost-filter-all">All services</label>
                                    </div>
                                    <hr class="dropdown-divider my-2">
                                    @foreach($information['cost_filters'] as $serviceType => $label)
                                        <div class="form-check {{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                            <input
                                                class="form-check-input cost-filter-option"
                                                type="checkbox"
                                                value="{{ $serviceType }}"
                                                data-label="{{ $label }}"
                                                id="cost-filter-{{ $serviceType }}"
                                                @checked(in_array($serviceType, $information['selected_cost_filters'], true))
                                            >
                                            <label class="form-check-label" for="cost-filter-{{ $serviceType }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <span class="badge bg-secondary" data-cost-currency>{{ $information['currency'] }}</span>
                        </div>
                    </div>
                    <div class="card-body-custom" data-cost-card>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="cost-item">
                                    <div class="cost-value" data-cost-field="weekly">{{ $information['total_cost_weekly'] }}</div>
                                    <div class="cost-label">Weekly</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="cost-item">
                                    <div class="cost-value" data-cost-field="monthly">{{ $information['total_cost_monthly'] }}</div>
                                    <div class="cost-label">Monthly</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="cost-item">
                                    <div class="cost-value" data-cost-field="yearly">{{ $information['total_cost_yearly'] }}</div>
                                    <div class="cost-label">Yearly</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="cost-item">
                                    <div class="cost-value" data-cost-field="two-yearly">{{ $information['total_cost_2_yearly'] }}</div>
                                    <div class="cost-label">2 Years</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resources Card -->
            <div class="col-12 col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">Server Resources</h5>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-4 col-md-2">
                                <div class="resource-item">
                                    <div class="resource-value">{{ $information['servers_summary']['cpu_sum'] }}</div>
                                    <div class="resource-label">CPU</div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="resource-item">
                                    <div class="resource-value">{{ number_format($information['servers_summary']['ram_mb_sum'] / 1024, 1) }}</div>
                                    <div class="resource-label">RAM GB</div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="resource-item">
                                    @if($information['servers_summary']['disk_gb_sum'] >= 1000)
                                        <div class="resource-value">{{ number_format($information['servers_summary']['disk_gb_sum'] / 1024, 1) }}</div>
                                        <div class="resource-label">Disk TB</div>
                                    @else
                                        <div class="resource-value">{{ $information['servers_summary']['disk_gb_sum'] }}</div>
                                        <div class="resource-label">Disk GB</div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="resource-item">
                                    <div class="resource-value">{{ number_format($information['servers_summary']['bandwidth_sum'] / 1024, 1) }}</div>
                                    <div class="resource-label">BW TB</div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="resource-item">
                                    <div class="resource-value">{{ $information['servers_summary']['locations_sum'] }}</div>
                                    <div class="resource-label">Locations</div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="resource-item">
                                    <div class="resource-value">{{ $information['servers_summary']['providers_sum'] }}</div>
                                    <div class="resource-label">Providers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Due Soon Section -->
        @if(Session::get('due_soon_amount') > 0 && !empty($information['due_soon']))
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">Due Soon</h5>
                        <span class="badge bg-warning text-dark">{{ count($information['due_soon']) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Due</th>
                                    <th>Price</th>
                                    <th class="text-center" style="width: 60px;">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($information['due_soon'] as $due_soon)
                                    <x-dashboard-service-row
                                        :service="$due_soon"
                                        :date-display="Carbon\Carbon::parse($due_soon->next_due_date)->diffForHumans()"
                                    />
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Recently Added Section -->
        @if(Session::get('recently_added_amount') > 0 && !empty($information['newest']))
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">Recently Added</h5>
                        <span class="badge bg-success">{{ count($information['newest']) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Added</th>
                                    <th>Price</th>
                                    <th class="text-center" style="width: 60px;">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($information['newest'] as $new)
                                    <x-dashboard-service-row
                                        :service="$new"
                                        :date-display="Carbon\Carbon::parse($new->created_at)->diffForHumans()"
                                    />
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Footer -->
        @if(Session::get('timer_version_footer', 0) === 1)
        <div class="row">
            <div class="col-12">
                <p class="text-muted small text-end mb-4">
                    Page loaded in {{ $information['execution_time'] }}s &middot;
                    My Idlers v{{ config('app.version') }} &middot;
                    Laravel v{{ Illuminate\Foundation\Application::VERSION }} &middot;
                    PHP v{{ PHP_VERSION }} &middot;
                    Rates by <a href="https://www.exchangerate-api.com" class="text-muted">Exchange Rate API</a>
                </p>
            </div>
        </div>
        @endif
    </div>

    @section('scripts')
    <script>
        window.addEventListener('load', function () {
            axios.defaults.headers.common = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            };

            const costsCard = document.querySelector('[data-cost-card]');
            const filterLabel = document.querySelector('[data-cost-filter-label]');
            const currencyBadge = document.querySelector('[data-cost-currency]');
            const allCheckbox = document.getElementById('cost-filter-all');
            const filterButton = document.getElementById('costFilterDropdown');
            const billingCycleSelect = document.getElementById('costBillingCycleFilter');
            const optionCheckboxes = Array.from(document.querySelectorAll('.cost-filter-option'));
            const allServiceTypes = optionCheckboxes.map(function (checkbox) {
                return Number(checkbox.value);
            });
            let activeRequest = 0;

            if (!costsCard || !filterLabel || !currencyBadge || !allCheckbox || !billingCycleSelect || optionCheckboxes.length === 0) {
                return;
            }

            function getSelectedTypes() {
                return optionCheckboxes
                    .filter(function (checkbox) {
                        return checkbox.checked;
                    })
                    .map(function (checkbox) {
                        return Number(checkbox.value);
                    });
            }

            function updateFilterLabel(selectedTypes) {
                if (selectedTypes.length === optionCheckboxes.length) {
                    filterLabel.textContent = 'All services';
                    return;
                }

                if (selectedTypes.length === 1) {
                    const selectedCheckbox = optionCheckboxes.find(function (checkbox) {
                        return checkbox.checked;
                    });

                    filterLabel.textContent = selectedCheckbox ? selectedCheckbox.dataset.label : 'All services';
                    return;
                }

                filterLabel.textContent = selectedTypes.length + ' selected';
            }

            function getSelectedBillingCycle() {
                return billingCycleSelect.value || 'all';
            }

            function applySelection(selectedTypes) {
                optionCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = selectedTypes.includes(Number(checkbox.value));
                });

                allCheckbox.checked = selectedTypes.length === optionCheckboxes.length;
                updateFilterLabel(selectedTypes);
            }

            function applyBillingCycleSelection(selectedBillingCycle) {
                const normalizedBillingCycle = Array.from(billingCycleSelect.options).some(function (option) {
                    return option.value === selectedBillingCycle;
                }) ? selectedBillingCycle : 'all';

                billingCycleSelect.value = normalizedBillingCycle;
            }

            function setLoading(isLoading) {
                costsCard.classList.toggle('opacity-50', isLoading);
                filterButton.disabled = isLoading;
                billingCycleSelect.disabled = isLoading;
                optionCheckboxes.forEach(function (checkbox) {
                    checkbox.disabled = isLoading;
                });
                allCheckbox.disabled = isLoading;
            }

            function updateCostFields(data) {
                const fields = {
                    weekly: data.total_cost_weekly,
                    monthly: data.total_cost_monthly,
                    yearly: data.total_cost_yearly,
                    'two-yearly': data.total_cost_2_yearly,
                };

                Object.keys(fields).forEach(function (key) {
                    const element = document.querySelector('[data-cost-field="' + key + '"]');
                    if (element) {
                        element.textContent = fields[key];
                    }
                });

                if (data.currency) {
                    currencyBadge.textContent = data.currency;
                }
            }

            function requestCosts(selectedTypes, selectedBillingCycle) {
                const requestId = activeRequest + 1;
                activeRequest = requestId;

                applySelection(selectedTypes);
                applyBillingCycleSelection(selectedBillingCycle);
                setLoading(true);

                axios.get(@json(route('costs.filter')), {
                    params: {
                        service_types: selectedTypes,
                        billing_cycle: selectedBillingCycle,
                    }
                }).then(function (response) {
                    if (requestId !== activeRequest) {
                        return;
                    }

                    const data = response.data || {};
                    const normalizedTypes = Array.isArray(data.selected_cost_filters) && data.selected_cost_filters.length > 0
                        ? data.selected_cost_filters.map(function (value) {
                            return Number(value);
                        })
                        : allServiceTypes;
                    const normalizedBillingCycle = typeof data.selected_billing_cycle_filter === 'string'
                        ? data.selected_billing_cycle_filter
                        : 'all';

                    applySelection(normalizedTypes);
                    applyBillingCycleSelection(normalizedBillingCycle);
                    updateCostFields(data);
                }).catch(function () {
                    applySelection(getSelectedTypes().length > 0 ? getSelectedTypes() : allServiceTypes);
                    applyBillingCycleSelection(getSelectedBillingCycle());
                }).finally(function () {
                    if (requestId === activeRequest) {
                        setLoading(false);
                    }
                });
            }

            optionCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const selectedTypes = getSelectedTypes();
                    requestCosts(
                        selectedTypes.length > 0 ? selectedTypes : allServiceTypes,
                        getSelectedBillingCycle()
                    );
                });
            });

            allCheckbox.addEventListener('change', function () {
                requestCosts(allServiceTypes, getSelectedBillingCycle());
            });

            billingCycleSelect.addEventListener('change', function () {
                const selectedTypes = getSelectedTypes();
                requestCosts(
                    selectedTypes.length > 0 ? selectedTypes : allServiceTypes,
                    getSelectedBillingCycle()
                );
            });

            applySelection(getSelectedTypes());
            applyBillingCycleSelection(getSelectedBillingCycle());
        });
    </script>
    @endsection
</x-app-layout>
