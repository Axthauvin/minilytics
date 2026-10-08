/**
 * Minilytics Data Import Controller
 * Manages analytics platform migration modal (Umami, GA4, Plausible, etc.)
 */

const ImportModal = {
    modal: null,
    selectedFile: null,
    selectedProvider: 'umami',
    currentStep: 1,
    autoSiteId: true,

    init() {
        this.modal = document.getElementById('importModal');
        if (!this.modal) return;

        this.bindEvents();
    },

    bindEvents() {
        // Open modal triggers
        const triggerBtns = [
            document.getElementById('btnWebsitesImport'),
            document.getElementById('btnSidebarImport')
        ];

        triggerBtns.forEach(btn => {
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.open();
                });
            }
        });

        // Provider card selection
        const providerCards = this.modal.querySelectorAll('.provider-card');
        providerCards.forEach(card => {
            card.addEventListener('click', () => {
                if (card.classList.contains('disabled')) {
                    const name = card.querySelector('.provider-name')?.textContent || 'This provider';
                    this.showError(`${name} importer is coming soon! Umami is currently available.`);
                    return;
                }

                providerCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                this.selectedProvider = card.dataset.provider || 'umami';
                const hiddenInput = document.getElementById('importSelectedProvider');
                if (hiddenInput) hiddenInput.value = this.selectedProvider;
            });
        });

        document.getElementById('btnImportProviderNext')?.addEventListener('click', () => this.setStep(2));
        document.getElementById('btnImportUploadBack')?.addEventListener('click', () => this.setStep(1));
        document.getElementById('btnImportDestinationBack')?.addEventListener('click', () => this.setStep(2));
        document.getElementById('btnImportUploadNext')?.addEventListener('click', () => {
            const serverPath = document.getElementById('importServerPath')?.value.trim();
            if (!this.selectedFile && !serverPath) {
                this.showError('Upload an export archive or provide a server path before continuing.');
                return;
            }
            this.setStep(3);
        });

        // File dropzone
        const dropzone = document.getElementById('importDropzone');
        const fileInput = document.getElementById('importFileInput');
        const removeFileBtn = document.getElementById('btnRemoveSelectedFile');

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', (e) => {
                if (e.target.closest('#btnRemoveSelectedFile')) return;
                fileInput.click();
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) {
                    this.handleFileSelected(e.target.files[0]);
                }
            });

            // Drag and drop
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                const files = e.dataTransfer?.files;
                if (files && files.length > 0) {
                    this.handleFileSelected(files[0]);
                }
            });
        }

        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.clearSelectedFile();
            });
        }

        // Toggle local server path
        const togglePathBtn = document.getElementById('btnToggleLocalPath');
        const localPathBox = document.getElementById('localPathContainer');
        if (togglePathBtn && localPathBox) {
            togglePathBtn.addEventListener('click', () => {
                const isHidden = localPathBox.style.display === 'none';
                localPathBox.style.display = isHidden ? 'block' : 'none';
                togglePathBtn.textContent = isHidden ? 'Hide server/local path option' : 'Or load from server/local folder or path';
            });
        }

        // Quick path chips
        const chips = this.modal.querySelectorAll('.quick-path-chip');
        const serverPathInput = document.getElementById('importServerPath');
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                const path = chip.dataset.path;
                if (serverPathInput && path) {
                    serverPathInput.value = path;
                    this.updateUploadNextState();
                    this.inspectServerPath(path);
                }
            });
        });

        if (serverPathInput) {
            serverPathInput.addEventListener('input', () => {
                const serverPath = serverPathInput.value.trim();
                this.updateUploadNextState();
                if (serverPath) {
                    this.inspectServerPath(serverPath);
                }
            });
        }

        // Target mode radios (new vs existing)
        const radioNew = document.getElementById('radioTargetNew');
        const radioExisting = document.getElementById('radioTargetExisting');
        const targetNewBox = document.getElementById('targetNewBox');
        const targetExistingBox = document.getElementById('targetExistingBox');

        const updateTargetMode = () => {
            const isExisting = radioExisting?.checked;
            if (targetNewBox) targetNewBox.style.display = isExisting ? 'none' : 'block';
            if (targetExistingBox) targetExistingBox.style.display = isExisting ? 'block' : 'none';
        };

        if (radioNew) radioNew.addEventListener('change', updateTargetMode);
        if (radioExisting) radioExisting.addEventListener('change', updateTargetMode);

        const newSiteName = document.getElementById('importNewSiteName');
        const newSiteId = document.getElementById('importNewSiteId');
        newSiteName?.addEventListener('input', () => {
            if (this.autoSiteId && newSiteId) {
                newSiteId.value = newSiteName.value.toLowerCase().trim()
                    .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            }
        });
        newSiteId?.addEventListener('input', () => { this.autoSiteId = false; });

        // Form submission
        const form = document.getElementById('importForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitImport();
            });
        }
    },

    open() {
        this.resetState();
        this.populateExistingSites();
        if (this.modal) this.modal.classList.add('active');
    },

    close() {
        if (this.modal) this.modal.classList.remove('active');
    },

    resetState() {
        this.selectedFile = null;
        this.selectedProvider = 'umami';
        this.autoSiteId = true;
        this.setStep(1);
        const form = document.getElementById('importForm');
        const progressView = document.getElementById('importProgressView');
        const successView = document.getElementById('importSuccessView');
        const errAlert = document.getElementById('importErrorAlert');

        if (form) {
            form.reset();
            form.style.display = 'block';
        }
        if (progressView) progressView.style.display = 'none';
        if (successView) successView.style.display = 'none';
        if (errAlert) errAlert.style.display = 'none';

        this.clearSelectedFile();

        const localPathBox = document.getElementById('localPathContainer');
        if (localPathBox) localPathBox.style.display = 'none';
        const detectedHint = document.getElementById('importDetectedWebsiteHint');
        if (detectedHint) detectedHint.hidden = true;
        this.updateUploadNextState();
    },

    setStep(step) {
        this.currentStep = step;
        this.modal?.querySelectorAll('[data-import-step]').forEach((view) => {
            view.hidden = Number(view.dataset.importStep) !== step;
        });
        this.modal?.querySelectorAll('[data-step-indicator]').forEach((indicator) => {
            const indicatorStep = Number(indicator.dataset.stepIndicator);
            indicator.classList.toggle('active', indicatorStep === step);
            indicator.classList.toggle('complete', indicatorStep < step);
        });
        const error = document.getElementById('importErrorAlert');
        if (error) error.style.display = 'none';
        this.updateUploadNextState();
    },

    async populateExistingSites() {
        const select = document.getElementById('importExistingSiteSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Loading sites...</option>';
        try {
            const data = await Api.getSites();
            const sites = data.sites || [];
            select.innerHTML = '';
            sites.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `${s.name} (${s.id})`;
                select.appendChild(opt);
            });
        } catch (err) {
            select.innerHTML = '<option value="">No existing website available</option>';
        }
    },

    handleFileSelected(file) {
        this.selectedFile = file;
        const card = document.getElementById('selectedFileCard');
        const nameElem = document.getElementById('selectedFileName');
        const sizeElem = document.getElementById('selectedFileSize');

        if (card && nameElem && sizeElem) {
            nameElem.textContent = file.name;
            sizeElem.textContent = this.formatFileSize(file.size);
            card.style.display = 'flex';
        }

        this.updateUploadNextState();

        // Auto-inspect the file to fill suggested metadata
        this.inspectFile(file);
    },

    clearSelectedFile() {
        this.selectedFile = null;
        const fileInput = document.getElementById('importFileInput');
        if (fileInput) fileInput.value = '';

        const card = document.getElementById('selectedFileCard');
        if (card) card.style.display = 'none';
        this.updateUploadNextState();
    },

    updateUploadNextState() {
        const nextButton = document.getElementById('btnImportUploadNext');
        if (!nextButton) return;

        const serverPath = document.getElementById('importServerPath')?.value.trim();
        const hasImportSource = Boolean(this.selectedFile || serverPath);
        nextButton.disabled = !hasImportSource;
        nextButton.setAttribute('aria-disabled', String(!hasImportSource));
    },

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    async inspectFile(file) {
        try {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('provider', this.selectedProvider);

            const res = await Api.inspectImport(fd);
            this.applyInspectionResults(res);
        } catch (err) {
            console.warn('Inspection preview could not be loaded:', err);
        }
    },

    async inspectServerPath(path) {
        try {
            const fd = new FormData();
            fd.append('server_path', path);
            fd.append('provider', this.selectedProvider);

            const res = await Api.inspectImport(fd);
            this.applyInspectionResults(res);
        } catch (err) {
            console.warn('Inspection of server path failed:', err);
        }
    },

    applyInspectionResults(res) {
        if (!res) return;

        const nameInput = document.getElementById('importNewSiteName');
        const domainInput = document.getElementById('importNewSiteDomain');
        const detectedHint = document.getElementById('importDetectedWebsiteHint');
        const hasDetectedDetails = Boolean(res.suggested_name || res.suggested_site_id || res.detected_host);
        if (detectedHint) detectedHint.hidden = !hasDetectedDetails;

        // Mirror the standard Add Website dialog: the name is suggested and the editable Site ID follows it.
        if (nameInput && !nameInput.value && res.suggested_name) {
            nameInput.value = res.suggested_name;
            nameInput.dispatchEvent(new Event('input'));
        }
        if (domainInput && !domainInput.value && res.detected_host) {
            domainInput.value = res.detected_host;
        }
    },

    showError(msg) {
        const errAlert = document.getElementById('importErrorAlert');
        if (errAlert) {
            errAlert.textContent = msg;
            errAlert.style.display = 'block';
            errAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            alert(msg);
        }
    },

    async submitImport() {
        const errAlert = document.getElementById('importErrorAlert');
        if (errAlert) errAlert.style.display = 'none';

        const serverPathInput = document.getElementById('importServerPath');
        const serverPath = serverPathInput ? serverPathInput.value.trim() : '';

        if (!this.selectedFile && !serverPath) {
            this.showError('Please upload an Umami export (.zip) or select a server path.');
            return;
        }

        const radioExisting = document.getElementById('radioTargetExisting');
        const isExisting = radioExisting?.checked;

        let targetSiteId = '';
        let targetSiteName = '';
        let targetSiteDomain = '';

        if (isExisting) {
            const select = document.getElementById('importExistingSiteSelect');
            targetSiteId = select ? select.value : '';
            if (!targetSiteId) {
                this.showError('Please select a destination website.');
                return;
            }
        } else {
            const idInput = document.getElementById('importNewSiteId');
            const nameInput = document.getElementById('importNewSiteName');
            const domainInput = document.getElementById('importNewSiteDomain');

            targetSiteId = idInput?.value.trim() || '';
            targetSiteName = nameInput?.value.trim() || '';
            targetSiteDomain = domainInput?.value.trim() || '';

            if (!targetSiteId) {
                this.showError('Please provide a Site ID for the new website.');
                return;
            }
        }

        const formData = new FormData();
        formData.append('provider', this.selectedProvider);
        formData.append('site_id', targetSiteId);
        formData.append('site_name', targetSiteName);
        formData.append('site_domain', targetSiteDomain);

        if (this.selectedFile) {
            formData.append('file', this.selectedFile);
        } else if (serverPath) {
            formData.append('server_path', serverPath);
        }

        // Show progress view
        const form = document.getElementById('importForm');
        const progressView = document.getElementById('importProgressView');
        const statusText = document.getElementById('importProgressStatus');

        if (form) form.style.display = 'none';
        if (progressView) progressView.style.display = 'block';
        if (statusText) statusText.textContent = 'Processing Umami export archive and inserting events...';

        try {
            const result = await Api.submitImport(formData);

            if (!result.success) {
                throw new Error(result.error || 'Import failed.');
            }

            // Display success
            this.showSuccessView(result);

        } catch (err) {
            if (progressView) progressView.style.display = 'none';
            if (form) form.style.display = 'block';
            this.showError(err.message || 'An error occurred during import.');
        }
    },

    showSuccessView(res) {
        const progressView = document.getElementById('importProgressView');
        const successView = document.getElementById('importSuccessView');

        if (progressView) progressView.style.display = 'none';
        if (successView) successView.style.display = 'block';

        const subtext = document.getElementById('importSuccessSubtext');
        if (subtext) {
            subtext.textContent = `Historical analytics were successfully imported into website '${res.site_name || res.site_id}'!`;
        }

        const elTotal = document.getElementById('resTotalEvents');
        const elViews = document.getElementById('resPageviews');
        const elEvents = document.getElementById('resCustomEvents');
        const elSess = document.getElementById('resSessions');
        const elRange = document.getElementById('resDateRange');

        if (elTotal) elTotal.textContent = (res.total_imported || 0).toLocaleString();
        if (elViews) elViews.textContent = (res.pageviews || 0).toLocaleString();
        if (elEvents) elEvents.textContent = (res.custom_events || 0).toLocaleString();
        if (elSess) elSess.textContent = (res.sessions || 0).toLocaleString();
        
        if (elRange) {
            if (res.date_start && res.date_end) {
                const s = res.date_start.split(' ')[0];
                const e = res.date_end.split(' ')[0];
                elRange.textContent = `${s} → ${e}`;
            } else {
                elRange.textContent = 'All time';
            }
        }

        const goToSiteBtn = document.getElementById('btnGoToImportedSite');
        if (goToSiteBtn) {
            goToSiteBtn.onclick = () => {
                this.close();
                // Refresh websites list and switch to the site
                if (window.WebsitesPage) window.WebsitesPage.load();
                if (window.App) {
                    window.App.switchSite(res.site_id);
                    window.location.hash = '#overview';
                }
            };
        }
    }
};

window.ImportModal = ImportModal;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    ImportModal.init();
});
