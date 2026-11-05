window.markdownEditor = (wire, property) => ({
    editor: null,
    observer: null,
    async activate() {
        if (this.editor) return;
        const [{default: Editor}] = await Promise.all([
            import('@toast-ui/editor'),
            import('@toast-ui/editor/dist/toastui-editor.css')
        ]);
        this.editor = new Editor({
            el: this.$refs.editor, height: '260px', initialEditType: 'markdown',
            previewStyle: 'tab', initialValue: wire.get(property) || '',
            usageStatistics: false, hideModeSwitch: true,
            events: {change: () => wire.set(property, this.editor.getMarkdown(), false)}
        });
        const root = this.$refs.editor;
        const repairAccessibility = () => {
            root.querySelectorAll('[aria-role]').forEach(element => {
                const role = element.getAttribute('aria-role');
                element.removeAttribute('aria-role');
                element.setAttribute('role', role === 'tabpanel' ? 'tablist' : role);
            });
            root.querySelector('.toastui-editor-tabs')?.setAttribute('aria-label', 'Tampilan editor Markdown');
            root.querySelectorAll('.more').forEach(element => element.setAttribute('aria-label', 'Opsi editor lainnya'));
            root.querySelector('.toastui-editor-pseudo-clipboard')?.setAttribute('aria-label', 'Papan salin editor');
            root.querySelectorAll('[contenteditable="true"]').forEach(element => {
                element.setAttribute('role', 'textbox');
                element.setAttribute('aria-label', 'Deskripsi Markdown');
                element.setAttribute('aria-multiline', 'true');
            });
        };
        repairAccessibility();
        this.observer = new MutationObserver(repairAccessibility);
        this.observer.observe(root, {childList: true, subtree: true});
        const tabs = [...root.querySelectorAll('.toastui-editor-tabs [role="tab"]')];
        tabs.forEach((tab, index) => tab.addEventListener('keydown', event => {
            let target;
            if (event.key === 'ArrowRight') target = tabs[(index + 1) % tabs.length];
            if (event.key === 'ArrowLeft') target = tabs[(index + tabs.length - 1) % tabs.length];
            if (event.key === 'Home') target = tabs[0];
            if (event.key === 'End') target = tabs[tabs.length - 1];
            if (event.key === 'Enter' || event.key === ' ') target = tab;
            if (target) { event.preventDefault(); target.click(); target.focus(); }
        }));
    },
    destroy() { this.observer?.disconnect(); this.editor?.destroy(); }
});
