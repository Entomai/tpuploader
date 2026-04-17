@once
    @push('header')
        <style>
            .tpuploader-upload-list {
                border: 1px solid var(--tblr-border-color);
                border-radius: 6px;
                padding: 1rem;
                background: var(--tblr-bg-surface-secondary);
            }

            .tpuploader-upload-item {
                border-top: 1px solid var(--tblr-border-color);
                padding-top: 0.75rem;
                margin-top: 0.75rem;
            }

            .tpuploader-upload-item:first-of-type {
                border-top: 0;
                padding-top: 0;
                margin-top: 0;
            }

            .tpuploader-upload-status-icon {
                width: 1.25rem;
                display: inline-flex;
                justify-content: center;
            }

            .tpuploader-upload-log-wrapper summary {
                cursor: pointer;
                width: fit-content;
            }

            .tpuploader-upload-log {
                white-space: pre-wrap;
                word-break: break-word;
                border-radius: 6px;
                padding: 0.75rem;
                background: var(--tblr-bg-surface);
                border: 1px solid var(--tblr-border-color);
            }
        </style>
    @endpush

    @push('footer')
        <script>
            (() => {
                const forms = document.querySelectorAll('[data-bb-toggle="tpuploader-upload-form"]')

                if (!forms.length) {
                    return
                }

                const notify = (type, message) => {
                    if (window.Botble) {
                        if (type === 'error' && typeof window.Botble.showError === 'function') {
                            window.Botble.showError(message)

                            return
                        }

                        if (type === 'success' && typeof window.Botble.showSuccess === 'function') {
                            window.Botble.showSuccess(message)

                            return
                        }
                    }

                    window.alert(message)
                }

                const formatMessage = (message, replacements) => {
                    return Object.entries(replacements).reduce((carry, [key, value]) => {
                        return carry.split(`:${key}`).join(value)
                    }, message)
                }

                const labelsFor = (form) => ({
                    waiting: form.dataset.waitingLabel,
                    uploading: form.dataset.uploadingLabel,
                    success: form.dataset.successLabel,
                    error: form.dataset.errorLabel,
                    log: form.dataset.logLabel,
                })

                const statusConfig = {
                    waiting: {
                        icon: '<i class="ti ti-clock"></i>',
                        badge: 'badge bg-secondary text-secondary-fg',
                    },
                    uploading: {
                        icon: '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>',
                        badge: 'badge bg-blue text-blue-fg',
                    },
                    success: {
                        icon: '<i class="ti ti-circle-check text-success"></i>',
                        badge: 'badge bg-green text-green-fg',
                    },
                    error: {
                        icon: '<i class="ti ti-alert-circle text-danger"></i>',
                        badge: 'badge bg-red text-red-fg',
                    },
                }

                const setStatus = (row, status, label) => {
                    const config = statusConfig[status] || statusConfig.waiting
                    const icon = row.querySelector('[data-tpuploader-status-icon]')
                    const badge = row.querySelector('[data-tpuploader-status-label]')

                    icon.innerHTML = config.icon
                    badge.className = config.badge
                    badge.textContent = label
                }

                const setLog = (row, message) => {
                    row.querySelector('[data-tpuploader-log]').textContent = message || ''
                }

                const createRow = (file, index, labels) => {
                    const row = document.createElement('div')
                    const logId = `tpuploader-upload-log-${Date.now()}-${index}`

                    row.className = 'tpuploader-upload-item'

                    const header = document.createElement('div')
                    header.className = 'd-flex align-items-center gap-2'

                    const icon = document.createElement('span')
                    icon.className = 'tpuploader-upload-status-icon'
                    icon.setAttribute('data-tpuploader-status-icon', 'true')

                    const name = document.createElement('strong')
                    name.className = 'text-truncate flex-fill'
                    name.textContent = file.name

                    const badge = document.createElement('span')
                    badge.setAttribute('data-tpuploader-status-label', 'true')

                    header.append(icon, name, badge)

                    const details = document.createElement('details')
                    details.className = 'tpuploader-upload-log-wrapper mt-2'
                    details.setAttribute('aria-controls', logId)

                    const summary = document.createElement('summary')
                    summary.className = 'text-secondary'
                    summary.textContent = labels.log

                    const log = document.createElement('pre')
                    log.id = logId
                    log.className = 'tpuploader-upload-log mt-2 mb-0'
                    log.setAttribute('data-tpuploader-log', 'true')

                    details.append(summary, log)
                    row.append(header, details)
                    setStatus(row, 'waiting', labels.waiting)
                    setLog(row, labels.waiting)

                    return row
                }

                const buildFormData = (form, file) => {
                    const formData = new FormData()
                    const fileInput = form.querySelector('[data-tpuploader-file-input]')

                    form.querySelectorAll('input, select, textarea').forEach((field) => {
                        if (!field.name || field.disabled || field === fileInput || field.type === 'file') {
                            return
                        }

                        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                            return
                        }

                        formData.append(field.name, field.value)
                    })

                    formData.append(form.dataset.uploadField, file, file.name)

                    return formData
                }

                const validationMessages = (payload) => {
                    if (!payload || !payload.errors) {
                        return null
                    }

                    return Object.values(payload.errors)
                        .reduce((messages, value) => messages.concat(value), [])
                        .join('\n')
                }

                const readResponse = async (response, fallbackMessage) => {
                    let payload = null

                    try {
                        payload = await response.json()
                    } catch (error) {
                        payload = null
                    }

                    const message = payload?.message || validationMessages(payload) || response.statusText || fallbackMessage

                    return {
                        error: !response.ok || Boolean(payload?.error),
                        message,
                        payload,
                    }
                }

                const uploadFile = async (form, file) => {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: buildFormData(form, file),
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })

                    return readResponse(response, form.dataset.requestFailedMessage)
                }

                forms.forEach((form) => {
                    form.addEventListener('submit', async (event) => {
                        event.preventDefault()

                        if (form.dataset.uploading === '1') {
                            return
                        }

                        const input = form.querySelector('[data-tpuploader-file-input]')
                        const files = Array.from(input?.files || [])

                        if (!files.length) {
                            notify('error', form.dataset.noFilesMessage)

                            return
                        }

                        const labels = labelsFor(form)
                        const list = form.querySelector('[data-tpuploader-upload-list]')
                        const submitButton = form.querySelector('button[type="submit"]')

                        list.innerHTML = ''
                        list.classList.remove('d-none')

                        const title = document.createElement('div')
                        title.className = 'fw-bold mb-2'
                        title.textContent = form.dataset.listTitle
                        list.append(title)

                        const rows = files.map((file, index) => {
                            const row = createRow(file, index, labels)
                            list.append(row)

                            return { file, row }
                        })

                        form.dataset.uploading = '1'
                        submitButton.disabled = true

                        let successful = 0
                        let failed = 0

                        for (const item of rows) {
                            setStatus(item.row, 'uploading', labels.uploading)
                            setLog(item.row, labels.uploading)

                            try {
                                const result = await uploadFile(form, item.file)

                                setLog(item.row, result.message)

                                if (result.error) {
                                    failed++
                                    setStatus(item.row, 'error', labels.error)
                                } else {
                                    successful++
                                    setStatus(item.row, 'success', labels.success)
                                }
                            } catch (error) {
                                failed++
                                setStatus(item.row, 'error', labels.error)
                                setLog(item.row, error.message || form.dataset.requestFailedMessage)
                            }
                        }

                        delete form.dataset.uploading
                        submitButton.disabled = false

                        const summary = formatMessage(
                            failed ? form.dataset.batchErrorMessage : form.dataset.batchSuccessMessage,
                            { success: successful, failed }
                        )

                        notify(failed ? 'error' : 'success', summary)
                    })
                })
            })()
        </script>
    @endpush
@endonce
