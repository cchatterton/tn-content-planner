/* global TNCP, wp */
(() => {
    'use strict';
    const __ = (text) => wp.i18n.__(text, 'tn-content-planner');
    const flags = ['local', 'related', 'children', 'siblings', 'parents'];
    const columns = ['title', 'slug'];
    const app = document.getElementById('tncp-app');
    const dialog = document.getElementById('tncp-dialog');
    let type = TNCP.types[0]?.name, plan = { revision: 0, rows: [] }, catalog = [], typeSettings = {};
    let patternsMine = false;
    let patternsPlan = { revision: 0, rows: [], catalog: {} };
    let selected = new Set(), dirty = false, busy = false, step = 1, editing = null;
    let reviewQueue = [], reviewIndex = 0, reviewTarget = 0, reviewDecision = '', reviewNewSlug = '', reviewApplied = 0, reviewSkipped = 0;
    let creationStatus = TNCP.types[0]?.can_publish ? 'publish' : 'draft';

    function el(tag, attributes = {}, children = []) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attributes)) {
            if (key === 'text') node.textContent = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else if (key === 'checked' || key === 'disabled' || key === 'hidden') node[key] = value;
            else node.setAttribute(key, value);
        }
        for (const child of children) node.append(child);
        return node;
    }
    function button(text, action, primary = false, disabled = false) {
        return el('button', { type: 'button', class: `button ${primary ? 'button-primary' : ''}`, text, onclick: action, disabled });
    }
    function postLink(id, label = `#${id}`) {
        return el('a', { href: `${TNCP.editor}?post=${Number(id)}&action=edit`, target: '_blank', rel: 'noopener noreferrer', text: label, title: __('Open in WordPress editor (new tab)'), onclick: event => event.stopPropagation() });
    }
    function mappedPost(id) {
        const post = catalog.find(item => item.id === id);
        const content = post?.has_content ? __('Has content') : __('No content');
        const featured = post?.has_featured_image ? __('Has featured image') : __('No featured image');
        const dots = el('span', { class: 'tncp-post-indicators', role: 'img', 'aria-label': `${content}; ${featured}`, title: `${content}; ${featured}` }, [
            el('span', { class: `tncp-post-dot${post?.has_content ? ' is-present' : ''}`, 'aria-hidden': 'true', title: content }),
            el('span', { class: `tncp-post-dot${post?.has_featured_image ? ' is-present' : ''}`, 'aria-hidden': 'true', title: featured })
        ]);
        return el('span', { class: 'tncp-post-reference' }, [postLink(id), dots]);
    }
    function announce(message, error = false) {
        const notice = document.getElementById('tncp-notice');
        notice.hidden = !message;
        notice.className = `notice ${error ? 'notice-error' : 'notice-success'}`;
        notice.querySelector('p').textContent = message;
    }
    async function api(action, data) {
        const response = await fetch(`${TNCP.api}${action}${action === 'patterns' ? '' : '/' + type}`, {
            method: data ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': TNCP.nonce },
            credentials: 'same-origin', ...(data ? { body: JSON.stringify(data) } : {})
        });
        let result;
        try { result = await response.json(); } catch { throw new Error(__('The server returned an unreadable response. Your edits are still here; try again.')); }
        if (!response.ok) throw new Error(result.message || __('The request failed. Try again.'));
        return result;
    }
    async function work(task) {
        if (busy) return;
        document.getElementById('tncp-loading').hidden = false;
        busy = true; app.inert = true; app.setAttribute('aria-busy', 'true');
        const controls = [...app.querySelectorAll('button, input, select')];
        const previous = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        try { await task(); } catch (error) { announce(error.message, true); }
        finally {
            document.getElementById('tncp-loading').hidden = true;
            busy = false; app.inert = false; app.setAttribute('aria-busy', 'false');
            controls.forEach((control, index) => { if (control.isConnected) control.disabled = previous[index]; });
        }
    }
    function ask(title, description, choices) {
        return new Promise(resolve => {
            document.getElementById('tncp-dialog-title').textContent = title;
            document.getElementById('tncp-dialog-description').replaceChildren(description);
            const actions = document.getElementById('tncp-dialog-actions');
            const options = choices.some(([value]) => value === 'cancel') ? choices : [...choices, ['cancel', __('Cancel')]];
            actions.replaceChildren(...options.map(([value, label, disabled = false]) => el('button', { value, class: 'button', text: label, disabled, ...(value === 'cancel' ? { autofocus: '' } : {}) })));
            dialog.returnValue = 'cancel';
            dialog.addEventListener('close', () => resolve(dialog.returnValue), { once: true });
            dialog.showModal();
        });
    }
    const uid = () => `r_${crypto.randomUUID()}`;
    function slug(value) {
        return value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^\p{L}\p{N}_%-]+/gu, '-').replace(/^-+|-+$/g, '');
    }
    function parseHTML(value) { const template = document.createElement('template'); template.innerHTML = value; return template.content; }
    function plain(value) { return parseHTML(value).textContent || ''; }
    function preview(value) {
        const source = parseHTML(value);
        const output = el('span');
        function copy(node, parent) {
            if (node.nodeType === Node.TEXT_NODE) { parent.append(document.createTextNode(node.textContent)); return; }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT'].includes(node.tagName)) return;
            const target = ['I', 'SPAN', 'STRONG', 'EM', 'B', 'BR'].includes(node.tagName) ? el(node.tagName.toLowerCase()) : el('span');
            if (['I', 'SPAN'].includes(node.tagName)) {
                if (node.hasAttribute('class')) target.className = node.getAttribute('class');
                if (node.hasAttribute('aria-hidden')) target.setAttribute('aria-hidden', node.getAttribute('aria-hidden'));
            }
            node.childNodes.forEach(child => copy(child, target)); parent.append(target);
        }
        source.childNodes.forEach(node => copy(node, output));
        return output;
    }
    function newRow() {
        return { id: uid(), title: '', slug: '', parent: '', template: 'single', flags: Object.fromEntries(flags.map(flag => [flag, false])), post_id: 0, baseline: null, confirmed: {} };
    }
    function parentId(row) {
        if (!row.parent) return 0;
        if (row.parent.startsWith('post:')) return Number(row.parent.slice(5));
        return plan.rows.find(item => `row:${item.id}` === row.parent)?.post_id || -1;
    }
    function parentKey(row) {
        if (!row.parent.startsWith('post:')) return row.parent;
        const mapped = plan.rows.find(item => item.post_id === Number(row.parent.slice(5)));
        return mapped ? `row:${mapped.id}` : row.parent;
    }
    function depth(row, seen = new Set()) {
        const key = row.id ? `row:${row.id}` : `post:${row.post_id}`;
        if (seen.has(key) || seen.size > 100) throw new Error(__('Choose a parent that does not create a circular hierarchy.'));
        seen.add(key);
        if (!row.parent) return 0;
        const parent = plan.rows.find(item => `row:${item.id}` === parentKey(row));
        if (parent) return 1 + depth(parent, seen);
        const post = catalog.find(item => `post:${item.id}` === row.parent);
        if (!post) return 1;
        return 1 + depth({ post_id: post.id, parent: post.parent ? `post:${post.parent}` : '' }, seen);
    }
    function pattern(row) { return `${type}-${depth(row)}-${row.template}-${flags.filter(flag => row.flags[flag]).length}`; }
    function orderedRows() {
        const result = [], seen = new Set();
        function visit(row) {
            if (seen.has(row.id)) return;
            seen.add(row.id); result.push(row);
            plan.rows.filter(child => parentKey(child) === `row:${row.id}`).forEach(visit);
        }
        plan.rows.filter(row => !parentKey(row).startsWith('row:')).forEach(visit);
        plan.rows.forEach(visit);
        return result;
    }
    function markDirty() { dirty = true; step = 1; }
    async function load() {
        await work(async () => {
            const saved = await api('plan');
            await api('refresh', { revision: saved.plan.revision, preserve_pending: true, scan: true, apply_approved: true });
            const data = await api('plan'); plan = data.plan; catalog = data.catalog; typeSettings = data.settings || {}; dirty = false; selected.clear(); step = 1; render();
        });
    }
    function parentPostValue(value) { return !value ? 0 : value.startsWith('post:') ? Number(value.slice(5)) : plan.rows.find(item => `row:${item.id}` === value)?.post_id || -1; }
    async function applyLinkedChange(row, field, value) {
        const wasDirty = dirty, old = row[field], metadata = field === 'template' || flags.includes(field);
        if (field === 'parent') {
            row[field] = value;
            try { plan.rows.forEach(item => depth(item)); } catch (error) { row[field] = old; announce(error.message, true); render(); return; }
            row[field] = old;
        }
        await work(async () => {
            const post = catalog.find(item => item.id === row.post_id);
            const result = await api('change', { revision: plan.revision, row_id: row.id, post_id: row.post_id, baseline: row.baseline, planning: post?.planning, field, value: field === 'parent' ? parentPostValue(value) : value, confirmed: true });
            if (flags.includes(field)) row.flags[field] = result.planning.flags[field];
            else row[field] = metadata ? result.planning.template : field === 'parent' ? (result.snapshot.parent ? `post:${result.snapshot.parent}` : '') : result.snapshot[field];
            row.baseline = result.snapshot;
            row.confirmed = { ...row.confirmed, [field]: false }; row.scanned = false;
            plan.revision = result.plan.revision;
            if (post) Object.assign(post, result.snapshot, { planning: result.planning });
            dirty = wasDirty || !result.plan.rows.some(item => item.id === row.id);
            render(); announce(metadata ? __('Planning setting saved.') : __('Approved change applied to WordPress.'));
        });
        render();
    }
    async function change(row, field, value) {
        if (busy || (flags.includes(field) ? row.flags[field] : row[field]) === value) return;
        const old = row[field];
        if (field === 'slug') value = slug(value);
        if (row.post_id && ['title', 'slug', 'parent'].includes(field)) {
            const message = field === 'parent' ? __('Do you want to move this post under the new parent?') : field === 'title' ? __('Do you want to change the linked post’s title or create a new item in the plan with the new name?') : __('Do you want to change the linked post’s slug or create a new item in the plan with this new slug?');
            const choices = [['update', field === 'parent' ? __('Move linked post') : __('Change linked post')]];
            if (field !== 'parent') choices.push(['new', __('Create new plan item')]);
            const decision = await ask(__('Linked post change'), `${message} ${__('Approving applies this change immediately.')}`, choices);
            if (decision === 'cancel') { render(); return; }
            if (decision === 'new') {
                const copy = { ...structuredClone(row), id: uid(), post_id: 0, baseline: null, confirmed: {} };
                copy[field] = value;
                if (field === 'title') copy.slug = slug(plain(value));
                let base = copy.slug || 'new-item', suffix = 2;
                while (plan.rows.some(item => item.slug === copy.slug) || catalog.some(post => post.slug === copy.slug)) copy.slug = `${base}-${suffix++}`;
                plan.rows.push(copy); editing = copy.id; markDirty(); render(); return;
            }
        }
        if (row.post_id) { await applyLinkedChange(row, field, value); return; }
        if (flags.includes(field)) { row.flags[field] = value; markDirty(); render(); return; }
        row[field] = value;
        try { plan.rows.forEach(item => depth(item)); } catch (error) { row[field] = old; announce(error.message, true); render(); return; }
        if (field === 'slug' && !row.post_id) {
            const matches = catalog.filter(post => post.slug === value);
            if (matches.length === 1 && !plan.rows.some(item => item.id !== row.id && item.post_id === matches[0].id)) {
                const post = matches[0]; row.post_id = post.id;
                row.baseline = { title: post.title, slug: post.slug, parent: post.parent };
                if (!row.title) row.title = post.title;
                if (!row.parent) row.parent = post.parent ? `post:${post.parent}` : '';
                announce(__('Existing slug found. This row is now linked to Post ID') + ` ${post.id}.`);
            } else if (matches.length) announce(__('This slug is ambiguous or already linked in the plan. Choose a unique slug.'), true);
        }
        markDirty(); setTimeout(render, 0);
    }
    async function confirmPending() {
        // CSV and automatic slug mapping follow the same confirmation rules as typed edits.
        for (const row of [...plan.rows]) {
            if (!row.post_id) {
                const matches = catalog.filter(post => post.slug === row.slug);
                if (matches.length === 1) {
                    const post = matches[0]; row.post_id = post.id; row.baseline = { title: post.title, slug: post.slug, parent: post.parent };
                }
            }
            if (!row.baseline) continue;
            for (const field of ['title', 'slug', 'parent']) {
                const desired = field === 'parent' ? parentId(row) : row[field];
                if (desired !== row.baseline[field] && !row.confirmed[field]) {
                    const wanted = row[field];
                    row[field] = field === 'parent' ? (row.baseline.parent ? `post:${row.baseline.parent}` : '') : row.baseline[field];
                    await change(row, field, wanted);
                    if (row[field] !== wanted) return false;
                }
            }
        }
        return true;
    }
    async function save() {
        if (busy) return;
        if (!await confirmPending()) { render(); return; }
        await work(async () => {
            plan = await api('save', { revision: plan.revision, rows: plan.rows }); dirty = false; render(); announce(__('Plan saved. Select rows, then choose Map Selected.'));
        });
    }
    function titleControl(row) {
        if (editing === row.id || selected.has(row.id)) return el('input', {
            type: 'text', value: row.title, 'aria-label': __('Title, HTML allowed'), required: '', 'data-title': row.id,
            onchange: event => change(row, 'title', event.target.value),
            onblur: () => { editing = null; }
        });
        return el('button', { type: 'button', class: 'tncp-title', 'aria-label': __('Edit title') + ': ' + plain(row.title), onclick: () => {
            editing = row.id; render(); const input = app.querySelector(`[data-title="${row.id}"]`); input?.focus();
        } }, [preview(row.title || __('Click to add a title'))]);
    }
    function pendingChange(row, field) {
        if (!row.post_id || !row.baseline) return false;
        if (field === 'parent') return parentId(row) !== row.baseline.parent;
        if (field === 'title' || field === 'slug') return row[field] !== row.baseline[field];
        const applied = catalog.find(post => post.id === row.post_id)?.planning;
        if (!applied) return false;
        return field === 'template' ? row.template !== applied.template : Boolean(row.flags[field]) !== Boolean(applied.flags[field]);
    }
    function markPendingFields(tr, row) {
        ['title', 'slug', 'parent', 'template', ...flags].forEach((field, index) => {
            if (!pendingChange(row, field)) return;
            const cell = tr.children[index + 1];
            const id = `tncp-pending-${row.id}-${field}`;
            cell.classList.add('tncp-pending'); cell.dataset.pending = field;
            const control = cell.querySelector('input, select, button');
            control?.setAttribute('aria-describedby', id);
            cell.append(el('span', { id, class: 'tncp-pending-label', text: __('Pending change') }));
        });
    }
    function rowView(row) {
        const tr = el('tr', { 'data-row': row.id });
        const check = el('input', { type: 'checkbox', checked: selected.has(row.id), 'aria-label': __('Select') + ' ' + plain(row.title), onchange: event => {
            event.target.checked ? selected.add(row.id) : selected.delete(row.id); render();
        } });
        const title = el('td', { class: 'tncp-title-cell' }, [titleControl(row)]);
        title.style.paddingInlineStart = `${Math.min(depth(row), 12) * 1.25 + 0.5}rem`;
        const parent = el('select', { 'aria-label': __('Parent'), onchange: event => change(row, 'parent', event.target.value) });
        parent.append(el('option', { value: '', text: __('— No parent —') }));
        for (const item of orderedRows()) {
            if (item.id !== row.id) parent.append(el('option', { value: `row:${item.id}`, text: plain(item.title) || item.slug || __('Untitled plan row') }));
        }
        const group = el('optgroup', { label: __('Existing posts') });
        catalog.filter(post => post.id !== row.post_id).forEach(post => group.append(el('option', { value: `post:${post.id}`, text: `${plain(post.title)} (#${post.id})` })));
        parent.append(group); parent.value = row.parent;
        const template = el('select', { 'aria-label': __('Template'), onchange: event => change(row, 'template', event.target.value) }, ['single', 'archive', 'custom'].map(value => el('option', { value, text: __(value[0].toUpperCase() + value.slice(1)) })));
        template.value = row.template;
        tr.append(el('td', {}, [check]), title,
            el('td', {}, [el('input', { type: 'text', value: row.slug, required: '', maxlength: '200', 'aria-label': __('Content slug'), onchange: event => change(row, 'slug', event.target.value) })]),
            el('td', {}, [parent]), el('td', {}, [template]));
        flags.forEach(flag => tr.append(el('td', { class: 'tncp-flag' }, [el('input', { type: 'checkbox', checked: row.flags[flag], 'aria-label': __(flag[0].toUpperCase() + flag.slice(1)), onchange: event => change(row, flag, event.target.checked) })])));
        tr.append(el('td', { class: 'tncp-pattern', text: pattern(row) }), el('td', {}, [row.post_id ? mappedPost(row.post_id) : document.createTextNode('—')]), el('td', {}, [el('button', { type: 'button', class: 'tncp-remove', title: __('Remove row'), 'aria-label': __('Remove row') + ': ' + (plain(row.title) || __('Untitled plan row')), onclick: async () => {
            if (plan.rows.some(item => parentKey(item) === `row:${row.id}`)) { announce(__('Move the child rows before removing their parent.'), true); return; }
            const post = catalog.find(item => item.id === row.post_id);
            const choices = [['remove', row.post_id ? __('Remove row only') : __('Remove row')]];
            let message = __('This removes the row from the plan.');
            if (row.post_id) {
                message += ` ${__('Linked post')}: ${plain(post?.title || row.title)} (#${row.post_id}). ${__('Keep this post, or move only this post to the WordPress bin. Other posts are not deleted.')}`;
                if (dirty) message += ' ' + __('Save the plan first to enable moving the linked post to the bin.');
                else if (!post?.can_trash) message += ' ' + __('Moving this post to the bin is unavailable for this user or site.');
                choices.push(['trash', __('Remove row & move post to bin'), dirty || !post?.can_trash]);
            }
            const description = el('span', { text: message });
            if (row.post_id) description.append(document.createTextNode(' '), postLink(row.post_id, __('Open linked post')));
            const answer = await ask(__('Remove plan row?'), description, choices);
            if (answer === 'remove') { plan.rows = plan.rows.filter(item => item.id !== row.id); selected.delete(row.id); markDirty(); render(); }
            if (answer === 'trash') {
                await work(async () => {
                    plan = await api('bin', { revision: plan.revision, row_id: row.id, confirmed: true }); selected.delete(row.id);
                    const data = await api('plan'); catalog = data.catalog; dirty = false; render();
                    announce(__('Plan row removed and linked post moved to the WordPress bin.'));
                });
            }
        } }, [el('span', { class: 'dashicons dashicons-trash', 'aria-hidden': 'true' })])]));
        markPendingFields(tr, row);
        return tr;
    }
    async function binSelected() {
        if (busy || dirty) return;
        const rows = orderedRows().filter(row => selected.has(row.id) && row.post_id).reverse();
        if (!rows.length) return;
        const removing = new Set(rows.map(row => row.id));
        for (const row of rows) {
            if (!catalog.find(post => post.id === row.post_id)?.can_trash) { announce(__('A selected post cannot be moved to the bin. Check permissions and whether the WordPress bin is enabled.'), true); return; }
            if (plan.rows.some(child => parentKey(child) === `row:${row.id}` && !removing.has(child.id))) {
                announce(__('Select the linked child rows too, or move the remaining child rows before binning their parent.'), true); return;
            }
        }
        const unlinked = plan.rows.filter(row => selected.has(row.id) && !row.post_id).length;
        const description = el('div', {}, [el('p', { text: `${rows.length} ${__('linked posts will move to the WordPress bin and their plan rows will be removed. You can restore the posts from the WordPress bin.')}` }),
            el('ul', { class: 'tncp-bin-list' }, rows.map(row => el('li', {}, [document.createTextNode(`${plain(row.title) || row.slug} `), postLink(row.post_id)])))]);
        if (unlinked) description.append(el('p', { text: `${unlinked} ${__('selected rows have no linked post and will remain in the plan.')}` }));
        if (await ask(__('Send selected to bin?'), description, [['bin', __('Send selected to bin')]]) !== 'bin') return;
        await work(async () => {
            let completed = 0;
            for (const row of rows) {
                try {
                    plan = await api('bin', { revision: plan.revision, row_id: row.id, confirmed: true });
                    selected.delete(row.id); catalog = catalog.filter(post => post.id !== row.post_id); completed++;
                    render();
                } catch (error) {
                    render(); announce(`${completed} ${__('of')} ${rows.length} ${__('posts moved to the bin. Remaining rows are still selected.')} ${error.message}`, true); return;
                }
            }
            render(); announce(`${completed} ${__('posts moved to the bin.')} ${unlinked ? __('Unlinked rows remain in the plan.') : ''}`);
        });
    }
    let headerFrame = 0;
    function positionTableHeaders() {
        headerFrame = 0;
        const admin = document.getElementById('wpadminbar');
        const top = Math.max(0, admin?.getBoundingClientRect().bottom || 0);
        app.querySelectorAll('.tncp-scroll table').forEach(table => {
            const head = table.tHead;
            if (!head || !table.getClientRects().length) return;
            const bounds = table.getBoundingClientRect();
            const offset = Math.min(Math.max(0, top - bounds.top), Math.max(0, bounds.height - head.offsetHeight));
            head.style.transform = `translateY(${offset}px)`;
            head.classList.toggle('tncp-header-pinned', offset > 0);
        });
    }
    function scheduleTableHeaders() { if (!headerFrame) headerFrame = requestAnimationFrame(positionTableHeaders); }
    window.addEventListener('scroll', scheduleTableHeaders, { passive: true });
    window.addEventListener('resize', scheduleTableHeaders, { passive: true });
    function render() {
        scheduleTableHeaders();
        const active = document.activeElement;
        const focusRow = active?.closest('[data-row]')?.dataset.row;
        const focusLabel = active?.getAttribute('aria-label');
        const selection = active?.tagName === 'INPUT' && active.type === 'text' ? [active.selectionStart, active.selectionEnd] : null;
        const currentType = TNCP.types.find(item => item.name === type);
        if (currentType) currentType.counts = { mapped: plan.rows.filter(row => row.post_id && catalog.some(post => post.id === row.post_id)).length, planned: plan.rows.length };
        const tabs = el('div', { class: 'tncp-tabs', role: 'tablist', 'aria-label': __('Post types') });
        const tabTypes = [...TNCP.types, { name: 'xp-patterns', label: __('XP Patterns') }];
        tabTypes.forEach(item => tabs.append(el('button', {
            type: 'button', role: 'tab', id: `tncp-tab-${item.name}`, 'aria-selected': String(type === item.name), 'aria-controls': 'tncp-panel', tabindex: type === item.name ? '0' : '-1', text: item.name === 'xp-patterns' ? item.label : `${item.label} ${item.counts?.mapped || 0}/${item.counts?.planned || 0}`, 'aria-label': item.name === 'xp-patterns' ? item.label : `${item.label}, ${item.counts?.mapped || 0} ${__('of')} ${item.counts?.planned || 0} ${__('mapped')}`, title: __('Mapped items / total plan items'),
            onkeydown: event => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault(); const index = tabTypes.findIndex(entry => entry.name === item.name);
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabTypes.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabTypes.length) % tabTypes.length;
                tabs.children[next].focus(); tabs.children[next].click();
            },
            onclick: async () => {
                if (busy) return;
                if (dirty) {
                    const decision = await ask(__('Unsaved plan'), __('Save your changes, discard them, or cancel to stay on this tab.'), [['discard', __('Discard')], ['cancel', __('Cancel')], ['save', __('Save plan now')]]);
                    if (decision === 'cancel') return;
                    if (decision === 'save') {
                        if (type === 'xp-patterns') await savePatterns(); else await save();
                        if (dirty) return;
                    }
                }
                const prior = type; type = item.name;
                await work(async () => { try {
                    if (type === 'xp-patterns') { patternsPlan = await api('patterns'); dirty = false; step = 1; render(); return; }
                    const saved = await api('plan');
                    await api('refresh', { revision: saved.plan.revision, preserve_pending: true, scan: true, apply_approved: true });
                    const data = await api('plan'); plan = data.plan; catalog = data.catalog; typeSettings = data.settings || {}; selected.clear(); dirty = false; step = 1; creationStatus = item.can_publish ? 'publish' : 'draft'; render();
                } catch (error) { type = prior; throw error; } });
                document.getElementById(`tncp-tab-${type}`)?.focus();
            }
        })));
        const panel = el('section', { id: 'tncp-panel', class: 'tncp-panel', role: 'tabpanel', 'aria-labelledby': `tncp-tab-${type}` });
        app.replaceChildren(tabs, panel);
        if (type === 'xp-patterns') { renderPatterns(panel); return; }
        const settings = el('dl', { class: 'tncp-type-settings', 'aria-label': __('Registered post type settings') });
        settings.append(el('div', { class: 'tncp-type-origin' + (typeSettings._builtin === false ? ' tncp-type-custom' : '') }, [el('dt', { class: 'screen-reader-text', text: __('Post type origin') }), el('dd', { text: (typeSettings._builtin === true ? __('Native') : typeSettings._builtin === false ? __('Custom') : __('Unavailable')) + ':' })]));
        const settingNames = { public: __('Public'), publicly_queryable: __('Publicly Queryable'), exclude_from_search: __('Include in Search'), hierarchical: __('Hierarchical') };
        Object.entries(settingNames).forEach(([key, label]) => {
            const rawValue = typeSettings[key];
            const value = key === 'publicly_queryable' && typeSettings._builtin === true ? true
                : key === 'exclude_from_search' && typeof rawValue === 'boolean' ? !rawValue : rawValue;
            const display = typeof value === 'boolean' ? el('dd', {}, [
                el('span', { class: value ? 'tncp-setting-yes' : 'tncp-setting-no', 'aria-hidden': 'true', text: value ? '✓' : '×' }),
                el('span', { class: 'screen-reader-text', text: value ? __('Enabled') : __('Disabled') })
            ]) : el('dd', { text: value === undefined ? __('Unavailable') : JSON.stringify(value) });
            settings.append(el('div', {}, [el('dt', { text: label }), display]));
        });
        panel.append(settings);
        if (step === 2) { renderReview(panel); return; }
        const file = el('input', { type: 'file', accept: '.csv,text/csv', class: 'screen-reader-text', id: 'tncp-csv', 'aria-label': __('Import CSV file'), onchange: event => importCSV(event.target.files[0]) });
        panel.append(el('div', { class: 'tncp-actions' }, [
            button(__('Import CSV'), () => file.click()), file, button(__('Download CSV template'), downloadTemplate),

        ]));
        if (!plan.rows.length) panel.append(el('div', { class: 'tncp-empty' }, [el('h3', { text: __('Start with the content you need') }), el('p', { text: __('Add your first row or import a CSV to build your work breakdown structure.') })]));
        else {
            const table = el('table', { class: 'widefat striped tncp-table' });
            const head = el('tr');
            const selectAll = el('input', { type: 'checkbox', id: 'tncp-select-all', 'aria-label': __('Select all rows'), checked: plan.rows.every(row => selected.has(row.id)), onchange: event => {
                selected = event.target.checked ? new Set(plan.rows.map(row => row.id)) : new Set(); render();
                document.getElementById('tncp-select-all')?.focus();
            } });
            selectAll.indeterminate = plan.rows.some(row => selected.has(row.id)) && !selectAll.checked;
            head.append(el('th', { scope: 'col' }, [selectAll]));
            [__('Title *'), __('Content slug *'), __('Parent'), __('Template'), __('Local'), __('Related'), __('Children'), __('Siblings'), __('Parents'), __('XP pattern'), __('Post ID'), __('Actions')].forEach(text => head.append(el('th', { scope: 'col', text })));
            table.append(el('thead', {}, [head]), el('tbody', {}, orderedRows().map(rowView)));
            panel.append(el('div', { class: 'tncp-scroll', tabindex: '0', role: 'region', 'aria-label': __('Content plan table') }, [table]));
        }
        panel.append(el('div', { class: 'tncp-actions tncp-footer' }, [
            button(__('Add row'), () => { const row = newRow(); plan.rows.push(row); editing = row.id; markDirty(); render(); app.querySelector(`[data-title="${row.id}"]`)?.focus(); }),
            button(__('Save plan'), save, true),
            button(__('Map Selected'), startReview, false, dirty || !selected.size),
            button(__('Send selected to bin'), binSelected, false, dirty || !plan.rows.some(row => selected.has(row.id) && row.post_id)),
            el('span', { text: `${plan.rows.length} ${__('rows')} · ${selected.size} ${__('selected')} · ${dirty ? __('Unsaved changes') : __('Saved plan')}`, role: 'status' })
        ]));
        if (focusRow && focusLabel) {
            const control = app.querySelector(`[data-row="${CSS.escape(focusRow)}"] [aria-label="${CSS.escape(focusLabel)}"]`);
            if (control) {
                control.focus({ preventScroll: true });
                if (selection && control.setSelectionRange) control.setSelectionRange(...selection);
            }
        }
    }
    function startReview() {
        reviewQueue = orderedRows().filter(row => selected.has(row.id)).map(row => row.id);
        reviewIndex = 0; reviewApplied = 0; reviewSkipped = 0;
        resetReviewItem(); step = 2; render();
    }
    function resetReviewItem() {
        const row = plan.rows.find(item => item.id === reviewQueue[reviewIndex]);
        reviewTarget = row?.post_id || 0; reviewDecision = '';
        reviewNewSlug = row?.slug || '';
        const base = reviewNewSlug; let suffix = 2;
        while (catalog.some(post => post.slug === reviewNewSlug) || plan.rows.some(item => item.id !== row?.id && item.slug === reviewNewSlug)) reviewNewSlug = `${base}-${suffix++}`;
    }
    function filterPatterns() {
        let visible = 0;
        app.querySelectorAll('[data-pattern]').forEach(element => {
            const row = patternsPlan.rows.find(item => item.key === element.dataset.pattern);
            element.hidden = patternsMine && row?.user_id !== patternsPlan.current_user_id;
            if (!element.hidden) visible++;
        });
        app.querySelectorAll('.tncp-pattern-filters button').forEach(control => {
            const active = (control.dataset.mine === 'true') === patternsMine;
            control.setAttribute('aria-pressed', String(active)); control.classList.toggle('button-primary', active);
        });
        const empty = document.getElementById('tncp-pattern-empty'); if (empty) empty.hidden = visible > 0;
        scheduleTableHeaders();
        const scroll = app.querySelector('.tncp-pattern-scroll'); if (scroll) scroll.hidden = visible === 0;
    }
    async function savePatterns() {
        await work(async () => {
            patternsPlan = await api('patterns', { revision: patternsPlan.revision, rows: patternsPlan.rows });
            dirty = false; render(); announce(__('Patterns saved.'));
        });
    }
    function renderPatterns(panel) {
        const filters = el('div', { class: 'tncp-pattern-filters', role: 'group', 'aria-label': __('Filter XP Patterns') });
        [[true, __('Show Mine')], [false, __('Show All')]].forEach(([mine, label]) => filters.append(el('button', {
            type: 'button', class: 'button' + (patternsMine === mine ? ' button-primary' : ''), 'aria-pressed': String(patternsMine === mine), 'data-mine': String(mine), text: label,
            onclick: () => { patternsMine = mine; filterPatterns(); }
        })));
        panel.append(el('div', { class: 'tncp-pattern-heading' }, [el('h2', { text: __('XP Patterns') }), filters]));
        if (!patternsPlan.rows.length) { panel.append(el('p', { text: __('No patterns yet. Scan a post type or save a content plan to get started.') })); return; }
        const table = el('table', { class: 'widefat striped tncp-patterns-table' });
        table.append(el('thead', {}, [el('tr', {}, [__('Pattern'), __('Content items'), __('Short description'), __('Status'), __('Assigned to'), __('Example post')].map(text => el('th', { scope: 'col', text })))]));
        const body = el('tbody');
        patternsPlan.rows.forEach(row => {
            const update = () => { dirty = true; document.getElementById('tncp-pattern-save-state').textContent = __('Unsaved changes'); };
            const description = el('input', { type: 'text', value: row.description, maxlength: '240', 'aria-label': `${__('Description')} ${row.key}`, oninput: event => { row.description = event.target.value; update(); } });
            const status = el('select', { 'aria-label': `${__('Status')} ${row.key}`, onchange: event => { row.status = event.target.value; update(); } }, ['todo', 'in-progress', 'done'].map(value => el('option', { value, text: value })));
            status.value = row.status;
            const assignee = el('select', { 'aria-label': `${__('Assigned to')} ${row.key}`, onchange: event => { row.user_id = Number(event.target.value); update(); filterPatterns(); } });
            assignee.append(el('option', { value: '0', text: __('— Unassigned —') }));
            const users = patternsPlan.users || [];
            users.forEach(user => assignee.append(el('option', { value: String(user.id), text: user.name })));
            if (row.user_id && !users.some(user => user.id === row.user_id)) assignee.append(el('option', { value: String(row.user_id), text: __('User unavailable — choose another') }));
            assignee.value = String(row.user_id || 0);
            const example = el('select', { 'aria-label': `${__('Example post')} ${row.key}`, onchange: event => { row.post_id = Number(event.target.value); update(); link.replaceChildren(...(row.post_id ? [postLink(row.post_id)] : [])); } });
            example.append(el('option', { value: '0', text: __('— No example —') }));
            const posts = patternsPlan.catalog[row.type] || [];
            posts.forEach(post => example.append(el('option', { value: String(post.id), text: `${plain(post.title) || __('Untitled')} (#${post.id})` })));
            if (row.post_id && !posts.some(post => post.id === row.post_id)) example.append(el('option', { value: String(row.post_id), text: __('Example unavailable — choose another') }));
            example.value = String(row.post_id);
            const link = el('span', { class: 'tncp-example-link' }, row.post_id && posts.some(post => post.id === row.post_id) ? [postLink(row.post_id)] : []);
            body.append(el('tr', { 'data-pattern': row.key }, [el('th', { scope: 'row', text: row.key }), el('td', { text: String(row.count) }), el('td', {}, [description]), el('td', {}, [status]), el('td', {}, [assignee]), el('td', {}, [el('div', { class: 'tncp-example-control' }, [example, link])])]));
        });
        panel.append(el('p', { id: 'tncp-pattern-empty', hidden: true, text: __('No XP Patterns are assigned to you.') }));
        table.append(body); panel.append(el('div', { class: 'tncp-scroll tncp-pattern-scroll', tabindex: '0', role: 'region', 'aria-label': __('XP pattern table') }, [table]));
        panel.append(el('div', { class: 'tncp-actions' }, [button(__('Save patterns'), savePatterns, true), el('span', { id: 'tncp-pattern-save-state', role: 'status', text: dirty ? __('Unsaved changes') : __('Saved patterns') })]));
        filterPatterns();
    }
    function matchText(value) {
        let decoded = value;
        try { decoded = decodeURIComponent(value); } catch { /* Keep malformed percent escapes as text. */ }
        return plain(decoded).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    }
    function suggestedMatches(row) {
        const title = plain(row.title).trim().toLowerCase().replace(/\s+/g, ' ');
        const titleWords = new Set(matchText(row.title).split(' ').filter(Boolean));
        const matches = catalog.filter(post => !plan.rows.some(item => item.id !== row.id && item.post_id === post.id && !(item.scanned && !['title', 'slug', 'parent', 'template', ...flags].some(field => pendingChange(item, field))))).map(post => {
            const exactSlug = row.slug.trim().toLowerCase() === post.slug.trim().toLowerCase();
            const exactTitle = title === plain(post.title).trim().toLowerCase().replace(/\s+/g, ' ');
            const sharedWords = [...new Set(matchText(post.title).split(' ').filter(Boolean))].filter(word => titleWords.has(word));
            const linked = row.post_id === post.id;
            return { post, linked, tier: exactSlug ? 0 : exactTitle ? 1 : 2, count: sharedWords.length,
                reason: exactSlug ? __('Exact slug') : exactTitle ? __('Exact title') : sharedWords.length ? `${sharedWords.length} ${sharedWords.length === 1 ? __('word matched') : __('words matched')}: ${sharedWords.join(', ')}` : __('Currently linked') };
        }).filter(match => match.tier < 2 || match.count > 0 || match.linked)
            .sort((left, right) => left.tier - right.tier || right.count - left.count || left.post.id - right.post.id);
        const visible = matches.slice(0, 5);
        const linked = matches.find(match => match.linked);
        if (linked && !visible.includes(linked)) visible.push(linked);
        return visible;
    }
    function reviewParent(value) {
        if (!value) return __('None');
        return value.startsWith('row:') ? plain(plan.rows.find(row => `row:${row.id}` === value)?.title || value) : plain(catalog.find(post => `post:${post.id}` === value)?.title || value);
    }
    function reviewSnapshot(post) { return { title: post.title, slug: post.slug, parent: post.parent, planning: post.planning }; }
    function renderReview(panel) {
        while (reviewIndex < reviewQueue.length && !plan.rows.some(row => row.id === reviewQueue[reviewIndex])) { selected.delete(reviewQueue[reviewIndex]); reviewIndex++; resetReviewItem(); }
        if (reviewIndex >= reviewQueue.length) {
            panel.append(el('h2', { text: __('Review complete') }), el('p', { text: `${reviewApplied} ${__('applied')} · ${reviewSkipped} ${__('skipped')}. ${__('Skipped items remain selected in the plan.')}` }), button(__('Back to plan'), () => { step = 1; render(); }));
            return;
        }
        const row = plan.rows.find(item => item.id === reviewQueue[reviewIndex]);
        if (!row) { panel.append(el('p', { text: __('This item is no longer in the saved plan. Return to the plan to select it again.') }), button(__('Back to plan'), () => { step = 1; render(); })); return; }
        const candidates = suggestedMatches(row);
        const target = catalog.find(post => post.id === reviewTarget);
        panel.append(el('p', { class: 'tncp-review-progress', role: 'status', text: `${__('Item')} ${reviewIndex + 1} ${__('of')} ${reviewQueue.length}` }),
            el('h2', { text: plain(row.title) }), el('p', { text: __('Does this content already exist? Choose a match and decide which values to keep, or create a separate post. Only this item will be applied.') }));
        const matches = el('fieldset', { class: 'tncp-matches' }, [el('legend', { text: __('Do any of these match?') })]);
        if (!candidates.length) matches.append(el('p', { text: __('No close matches found in this post type.') }));
        candidates.forEach(match => {
            const input = el('input', { type: 'radio', name: 'tncp-match', value: String(match.post.id), checked: reviewTarget === match.post.id,
                onchange: () => { reviewTarget = match.post.id; reviewDecision = ''; render(); } });
            matches.append(el('label', { class: 'tncp-match' }, [input, el('span', {}, [el('strong', { text: plain(match.post.title) }),
                el('span', { class: 'tncp-match-detail' }, [document.createTextNode(`/${match.post.slug} · `), postLink(match.post.id), document.createTextNode(` · ${match.reason}${match.linked ? ' · ' + __('Currently linked') : ''}`)])])]));
        });
        matches.append(el('p', { class: 'description', text: __('Matches are ranked by exact slug, exact title, then shared title words. No match is accepted automatically.') }));
        panel.append(matches);
        const table = el('table', { class: 'widefat tncp-compare' });
        table.append(el('thead', {}, [el('tr', {}, [el('th', { scope: 'col', text: __('Field') }), el('th', { scope: 'col', text: __('Source — your plan') }), el('th', { scope: 'col', text: __('Destination — WordPress') })])]));
        const body = el('tbody');
        const fields = [
            [__('Title'), row.title, target?.title], [__('Slug'), row.slug, target?.slug],
            [__('Parent'), reviewParent(row.parent), target ? reviewParent(target.parent ? `post:${target.parent}` : '') : null],
            [__('Template'), row.template, target?.planning.template],
            [__('Relationships'), flags.filter(flag => row.flags[flag]).join(', ') || __('None'), target ? flags.filter(flag => target.planning.flags[flag]).join(', ') || __('None') : null]
        ];
        fields.forEach(([label, source, destination]) => {
            const sourceCell = el('td', { text: source });
            const destinationCell = el('td', { text: destination ?? __('Choose a match above') });
            if (label === __('Title') || label === __('Slug')) {
                if (row.post_id) sourceCell.replaceChildren(postLink(row.post_id, source));
                if (target) destinationCell.replaceChildren(postLink(target.id, destination));

            }
            body.append(el('tr', { class: target && source !== destination ? 'tncp-difference' : '' }, [el('th', { scope: 'row', text: label }), sourceCell, destinationCell]));
        });
        table.append(body); panel.append(el('div', { class: 'tncp-scroll', tabindex: '0', role: 'region', 'aria-label': __('Source and destination comparison') }, [table]));
        const choices = el('fieldset', { class: 'tncp-decisions' }, [el('legend', { text: __('What should happen to this item?') })]);
        [['source', __('Accept source'), __('Use the plan’s title, slug, parent, template and relationship flags on the chosen post. Its content and publication status stay unchanged.')],
            ['destination', __('Accept destination'), __('Link this plan item to the chosen post and adopt its values. The WordPress post is not changed.')],
            ['new', __('Create new'), __('Create a separate post from this plan item. Existing posts are kept; planned children will follow the new post when you review them.')]].forEach(([value, label, description]) => {
                choices.append(el('label', { class: 'tncp-decision' }, [el('input', { type: 'radio', name: 'tncp-decision', value, checked: reviewDecision === value, disabled: value !== 'new' && !target, onchange: () => { reviewDecision = value; render(); } }), el('span', {}, [el('strong', { text: label }), el('span', { class: 'tncp-match-detail', text: description })])]));
            });
        panel.append(choices);
        if (reviewDecision === 'new') {
            const canPublish = TNCP.types.find(item => item.name === type)?.can_publish;
            const status = el('select', { id: 'tncp-creation-status', onchange: event => { creationStatus = event.target.value; render(); document.getElementById('tncp-creation-status')?.focus(); } }, [
                el('option', { value: 'publish', text: __('Published'), disabled: !canPublish }), el('option', { value: 'draft', text: __('Draft') })]);
            status.value = creationStatus;
            panel.append(el('div', { class: 'tncp-actions' }, [el('label', { for: 'tncp-new-slug', text: __('New post slug') }), el('input', { id: 'tncp-new-slug', type: 'text', value: reviewNewSlug, oninput: event => { reviewNewSlug = event.target.value; } }),
                el('label', { for: 'tncp-creation-status', text: __('New post status') }), status]));
            panel.append(el('p', { class: 'description', text: creationStatus === 'publish' ? __('The new post will be published when you apply this item.') : __('The new post will be saved as a draft.') }));
        }
        const blockedParent = parentId(row) < 0 && reviewDecision !== 'destination';
        if (blockedParent) panel.append(el('p', { class: 'tncp-review-warning', text: __('This item has an uncreated parent. Review that parent first, or skip this item for now.') }));
        panel.append(el('div', { class: 'tncp-actions' }, [
            button(__('Back to plan'), () => { step = 1; render(); }),
            button(__('Skip for now'), () => { reviewSkipped++; reviewIndex++; resetReviewItem(); render(); }),
            button(__('Reload this item'), async () => { await work(async () => { const data = await api('plan'); plan = data.plan; catalog = data.catalog; typeSettings = data.settings || {}; resetReviewItem(); render(); }); }),
            button(reviewIndex + 1 === reviewQueue.length ? __('Apply & finish') : __('Apply & next'), async () => {
                await work(async () => {
                    const result = await api('resolve', { revision: plan.revision, row_id: row.id, decision: reviewDecision, target_id: reviewTarget,
                        target_snapshot: target ? reviewSnapshot(target) : null, new_slug: slug(reviewNewSlug), creation_status: creationStatus });
                    if (!result.completed.length) throw new Error(result.errors.join(' ') || __('This item could not be applied. Review it and try again.'));
                    plan = result.plan; selected.delete(row.id); dirty = false;
                    reviewApplied++; reviewIndex++;
                    // Advance only after a successful resolution. If catalog refresh fails, retry loading without reapplying.
                    let refreshError = '';
                    try { const data = await api('plan'); catalog = data.catalog; } catch (error) { refreshError = error.message; }
                    resetReviewItem(); render();
                    if (refreshError) announce(refreshError, true);
                    else if (result.errors.length) announce(result.errors.join(' '), true);
                    else announce(__('Item applied.'));
                });
            }, true, !reviewDecision || blockedParent)
        ]));
    }
    function parseCSV(text) {
        const records = []; let record = [], field = '', quoted = false, closed = false;
        text = text.replace(/^\uFEFF/, '');
        for (let index = 0; index < text.length; index++) {
            const char = text[index];
            if (quoted) {
                if (char === '"') { if (text[index + 1] === '"') { field += '"'; index++; } else { quoted = false; closed = true; } }
                else field += char;
            } else if (char === '"' && !field && !closed) quoted = true;
            else if (char === ',' || char === '\n' || char === '\r') {
                record.push(field); field = ''; closed = false;
                if (char !== ',') { if (record.some(value => value !== '')) records.push(record); record = []; if (char === '\r' && text[index + 1] === '\n') index++; }
            } else { if (closed || char === '"') throw new Error(__('Invalid CSV quoting. Use double quotes around fields containing commas.')); field += char; }
        }
        if (quoted) throw new Error(__('The CSV contains an unclosed quoted field.'));
        record.push(field); if (record.some(value => value !== '')) records.push(record);
        return records;
    }
    function downloadTemplate() {
        const url = URL.createObjectURL(new Blob(['\uFEFF' + columns.join(',') + '\r\n'], { type: 'text/csv;charset=utf-8' }));
        const link = el('a', { href: url, download: `tn-content-planner-${type}-template.csv` }); link.click(); URL.revokeObjectURL(url);
    }
    async function importCSV(file) {
        if (!file) return;
        try {
            if (file.size > 1024 * 1024) throw new Error(__('Use a CSV smaller than 1 MB.'));
            const records = parseCSV(await file.text());
            const headers = records.shift()?.map(value => value.trim().toLowerCase());
            if (!headers || headers.length !== columns.length || headers.some((value, index) => value !== columns[index])) throw new Error(__('Use the column order in the downloadable CSV template.'));
            if (records.length + plan.rows.length > 2000) throw new Error(__('Use no more than 2,000 plan rows per post type.'));
            const imported = records.map((record, index) => {
                if (record.length !== columns.length) throw new Error(`${__('Wrong number of columns in CSV row')} ${index + 2}.`);
                const data = Object.fromEntries(headers.map((key, column) => [key, record[column]]));
                const row = newRow(); row.title = data.title; row.slug = slug(data.slug);
                if (!plain(row.title).trim() || !row.slug) throw new Error(`${__('Invalid title or slug in CSV row')} ${index + 2}.`);
                return row;
            });
            const combined = [...plan.rows, ...imported];
            if (new Set(combined.map(row => row.slug)).size !== combined.length) throw new Error(__('Duplicate slugs found. CSV imports append rows; they do not replace existing plan rows.'));
            plan.rows = combined;
            markDirty(); render(); announce(`${imported.length} ${__('rows imported. Review and save the plan. The import has not changed any posts.')}`);
        } catch (error) { announce(error.message, true); }
    }
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    if (type) load(); else { app.replaceChildren(el('p', { text: __('No editable post types are available.') })); app.setAttribute('aria-busy', 'false'); }
})();
