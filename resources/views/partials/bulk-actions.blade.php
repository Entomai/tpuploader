@once
    @push('footer')
        <x-core::modal.action
            id="tpuploader-bulk-action-modal"
            type="danger"
            icon="ti ti-adjustments-horizontal"
            :title="trans('plugins/tpuploader::tpuploader.actions')"
            :submit-button-label="trans('plugins/tpuploader::tpuploader.actions')"
            :submit-button-attrs="['id' => 'tpuploader-confirm-bulk-action']"
            size="lg"
            data-activate-title="{{ trans('plugins/tpuploader::tpuploader.bulk_activate_title') }}"
            data-activate-confirmation="{{ trans('plugins/tpuploader::tpuploader.bulk_activate_confirmation') }}"
            data-activate-submit="{{ trans('plugins/tpuploader::tpuploader.bulk_activate_submit') }}"
            data-deactivate-title="{{ trans('plugins/tpuploader::tpuploader.bulk_deactivate_title') }}"
            data-deactivate-confirmation="{{ trans('plugins/tpuploader::tpuploader.bulk_deactivate_confirmation') }}"
            data-deactivate-submit="{{ trans('plugins/tpuploader::tpuploader.bulk_deactivate_submit') }}"
            data-remove-title="{{ trans('plugins/tpuploader::tpuploader.bulk_remove_title') }}"
            data-remove-confirmation="{{ trans('plugins/tpuploader::tpuploader.bulk_remove_confirmation') }}"
            data-remove-submit="{{ trans('plugins/tpuploader::tpuploader.bulk_remove_submit') }}"
            data-results-title="{{ trans('plugins/tpuploader::tpuploader.bulk_action_results') }}"
            data-reload-label="{{ trans('plugins/tpuploader::tpuploader.bulk_action_reload') }}"
            data-status-activate="{{ trans('plugins/tpuploader::tpuploader.bulk_status_activated') }}"
            data-status-deactivate="{{ trans('plugins/tpuploader::tpuploader.bulk_status_deactivated') }}"
            data-status-remove="{{ trans('plugins/tpuploader::tpuploader.bulk_status_removed') }}"
            data-status-error="{{ trans('plugins/tpuploader::tpuploader.bulk_status_failed') }}"
            data-no-selection="{{ trans('plugins/tpuploader::tpuploader.bulk_action_no_selection') }}"
            data-request-failed="{{ trans('plugins/tpuploader::tpuploader.bulk_action_request_failed') }}"
        >
            <div data-tpuploader-bulk-confirmation>
                <p
                    class="text-secondary"
                    data-tpuploader-bulk-confirmation-text
                ></p>
                <div class="text-start">
                    <div class="fw-bold mb-2">
                        {{ trans('plugins/tpuploader::tpuploader.selected_plugins') }}
                    </div>
                    <ul
                        class="mb-0 ps-4"
                        data-tpuploader-bulk-selected-list
                    ></ul>
                </div>
            </div>

            <div
                class="d-none text-start"
                data-tpuploader-bulk-results
            >
                <div
                    class="alert mb-3"
                    role="status"
                    data-tpuploader-bulk-summary
                ></div>
                <div
                    class="d-grid gap-2"
                    data-tpuploader-bulk-result-list
                ></div>
            </div>
        </x-core::modal.action>

        <script>
            (() => {
                const modal = document.getElementById('tpuploader-bulk-action-modal')
                const bulkActionsButton = document.querySelector('[data-tpuploader-bulk-actions]')
                const actionButtons = Array.from(document.querySelectorAll('[data-tpuploader-bulk-action]'))
                const selectVisible = document.querySelector('[data-tpuploader-select-visible]')
                const selectedCount = document.querySelector('[data-tpuploader-selected-count]')

                if (!modal || !bulkActionsButton || !actionButtons.length || !selectVisible || !selectedCount) {
                    return
                }

                const confirmButton = modal.querySelector('#tpuploader-confirm-bulk-action')
                const closeButton = modal.querySelector('.modal-footer [data-bs-dismiss="modal"]')
                const title = modal.querySelector('.modal-body h3')
                const statusBar = modal.querySelector('.modal-status')
                const modalIcon = modal.querySelector('.modal-body > .mb-2 .icon')
                const confirmation = modal.querySelector('[data-tpuploader-bulk-confirmation]')
                const confirmationText = modal.querySelector('[data-tpuploader-bulk-confirmation-text]')
                const selectedList = modal.querySelector('[data-tpuploader-bulk-selected-list]')
                const results = modal.querySelector('[data-tpuploader-bulk-results]')
                const summary = modal.querySelector('[data-tpuploader-bulk-summary]')
                const resultList = modal.querySelector('[data-tpuploader-bulk-result-list]')
                const defaultCloseLabel = closeButton.textContent.trim()
                let pendingSelections = []
                let pendingAction = null

                const pluginSelections = () => Array.from(
                    document.querySelectorAll('[data-tpuploader-plugin-selection]:not(:disabled)')
                )

                const selectedPlugins = () => pluginSelections().filter((checkbox) => checkbox.checked)

                const compatibleSelections = (action) => selectedPlugins().filter((checkbox) => {
                    if (action === 'activate') {
                        return checkbox.dataset.pluginStatus === 'not-activated'
                    }

                    if (action === 'deactivate') {
                        return checkbox.dataset.pluginStatus === 'activated'
                    }

                    return action === 'remove'
                })

                const visiblePlugins = () => pluginSelections().filter((checkbox) => {
                    const pluginItem = checkbox.closest('.plugin-item')

                    return pluginItem && window.getComputedStyle(pluginItem).display !== 'none'
                })

                const setDisabled = (element, disabled) => {
                    element.disabled = disabled
                    element.classList.toggle('disabled', disabled)
                    element.setAttribute('aria-disabled', disabled ? 'true' : 'false')
                }

                const syncSelectionControls = () => {
                    const selected = selectedPlugins()
                    const visible = visiblePlugins()
                    const visibleSelected = visible.filter((checkbox) => checkbox.checked)

                    selectedCount.textContent = selected.length
                    setDisabled(bulkActionsButton, selected.length === 0)
                    selectVisible.disabled = visible.length === 0
                    selectVisible.checked = visible.length > 0 && visibleSelected.length === visible.length
                    selectVisible.indeterminate = visibleSelected.length > 0 && visibleSelected.length < visible.length

                    actionButtons.forEach((button) => {
                        const action = button.dataset.tpuploaderBulkAction
                        const count = compatibleSelections(action).length
                        const countElement = button.querySelector(`[data-tpuploader-action-count="${action}"]`)

                        if (countElement) {
                            countElement.textContent = count
                        }

                        setDisabled(button, count === 0)
                    })
                }

                const errorMessage = (payload, fallback) => {
                    if (!payload) {
                        return fallback
                    }

                    if (payload.errors) {
                        return Object.values(payload.errors).flat().join('\n')
                    }

                    return payload.message || fallback
                }

                const removeClassesWithPrefixes = (element, prefixes) => {
                    Array.from(element.classList).forEach((className) => {
                        if (prefixes.some((prefix) => className.startsWith(prefix))) {
                            element.classList.remove(className)
                        }
                    })
                }

                const configureModalStyle = (action) => {
                    const color = action === 'activate' ? 'success' : action === 'deactivate' ? 'warning' : 'danger'

                    removeClassesWithPrefixes(confirmButton, ['btn-'])
                    confirmButton.classList.add('btn', `btn-${color}`)

                    if (statusBar) {
                        removeClassesWithPrefixes(statusBar, ['bg-'])
                        statusBar.classList.add(`bg-${color}`)
                    }

                    if (modalIcon) {
                        removeClassesWithPrefixes(modalIcon, ['text-'])
                        modalIcon.classList.add(`text-${color}`)
                    }
                }

                const createResult = (result) => {
                    const item = document.createElement('div')
                    item.className = 'border rounded p-3'

                    const header = document.createElement('div')
                    header.className = 'd-flex align-items-center gap-2'

                    const icon = document.createElement('i')
                    icon.className = result.error
                        ? 'ti ti-alert-circle text-danger'
                        : 'ti ti-circle-check text-success'

                    const name = document.createElement('strong')
                    name.className = 'text-break flex-fill'
                    name.textContent = result.name || result.plugin

                    const badge = document.createElement('span')
                    badge.className = result.error
                        ? 'badge bg-red text-red-fg'
                        : 'badge bg-green text-green-fg'
                    badge.textContent = result.error
                        ? modal.dataset.statusError
                        : modal.dataset[`status${pendingAction.charAt(0).toUpperCase()}${pendingAction.slice(1)}`]

                    const log = document.createElement('pre')
                    log.className = 'tpuploader-bulk-log small text-secondary border rounded p-2 mt-2 mb-0'
                    log.textContent = result.message || modal.dataset.requestFailed

                    header.append(icon, name, badge)
                    item.append(header, log)

                    return item
                }

                const showResults = (payload) => {
                    const responseData = payload.data || {}
                    const items = Array.isArray(responseData.results) ? responseData.results : []

                    title.textContent = modal.dataset.resultsTitle
                    confirmation.classList.add('d-none')
                    results.classList.remove('d-none')
                    summary.className = payload.error ? 'alert alert-warning mb-3' : 'alert alert-success mb-3'
                    summary.textContent = payload.message || modal.dataset.requestFailed
                    resultList.replaceChildren(...items.map(createResult))
                    confirmButton.classList.add('d-none')
                    closeButton.textContent = modal.dataset.reloadLabel
                    modal.dataset.completed = '1'

                    if (payload.error) {
                        Botble.showError(payload.message)
                    } else {
                        Botble.showSuccess(payload.message)
                    }
                }

                document.addEventListener('change', (event) => {
                    if (event.target.matches('[data-tpuploader-plugin-selection]')) {
                        syncSelectionControls()
                    }

                    if (event.target.matches('[data-tpuploader-select-visible]')) {
                        visiblePlugins().forEach((checkbox) => {
                            checkbox.checked = event.target.checked
                        })

                        syncSelectionControls()
                    }

                    if (event.target.matches('[data-bb-toggle="change-filter-plugin-status"]')) {
                        setTimeout(syncSelectionControls)
                    }
                })

                document.addEventListener('keyup', (event) => {
                    if (event.target.matches('[data-bb-toggle="change-search"]')) {
                        setTimeout(syncSelectionControls)
                    }
                })

                document.addEventListener('click', (event) => {
                    if (event.target.closest('button[data-bb-toggle="change-filter-plugin-status"]')) {
                        setTimeout(syncSelectionControls)
                    }
                })

                actionButtons.forEach((actionButton) => {
                    actionButton.addEventListener('click', () => {
                        pendingAction = actionButton.dataset.tpuploaderBulkAction
                        pendingSelections = compatibleSelections(pendingAction)

                        if (!pendingSelections.length) {
                            Botble.showError(modal.dataset.noSelection)

                            return
                        }

                        const titleKey = `${pendingAction}Title`
                        const confirmationKey = `${pendingAction}Confirmation`
                        const submitKey = `${pendingAction}Submit`

                        modal.dataset.completed = '0'
                        title.textContent = modal.dataset[titleKey]
                        confirmationText.textContent = modal.dataset[confirmationKey].replace(':count', pendingSelections.length)
                        selectedList.replaceChildren(...pendingSelections.map((checkbox) => {
                            const item = document.createElement('li')
                            item.className = 'text-break'
                            item.textContent = checkbox.dataset.pluginName || checkbox.value

                            return item
                        }))
                        confirmation.classList.remove('d-none')
                        results.classList.add('d-none')
                        resultList.replaceChildren()
                        confirmButton.classList.remove('d-none')
                        confirmButton.textContent = modal.dataset[submitKey]
                        confirmButton.dataset.url = actionButton.dataset.url
                        closeButton.textContent = defaultCloseLabel
                        configureModalStyle(pendingAction)

                        window.bootstrap.Modal.getOrCreateInstance(modal).show()
                    })
                })

                confirmButton.addEventListener('click', async () => {
                    if (!pendingSelections.length || !pendingAction) {
                        return
                    }

                    const button = $(confirmButton)
                    Botble.showButtonLoading(button)

                    try {
                        const response = await fetch(confirmButton.dataset.url, {
                            method: pendingAction === 'remove' ? 'DELETE' : 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ plugins: pendingSelections.map((checkbox) => checkbox.value) }),
                        })
                        const payload = await response.json().catch(() => null)

                        if (!response.ok || !payload) {
                            throw new Error(errorMessage(payload, modal.dataset.requestFailed))
                        }

                        showResults(payload)
                    } catch (error) {
                        Botble.showError(error.message || modal.dataset.requestFailed)
                    } finally {
                        Botble.hideButtonLoading(button)
                    }
                })

                modal.addEventListener('hidden.bs.modal', () => {
                    if (modal.dataset.completed === '1') {
                        window.location.reload()
                    }
                })

                syncSelectionControls()
            })()
        </script>
    @endpush
@endonce
