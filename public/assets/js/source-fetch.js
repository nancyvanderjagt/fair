document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector(
        '#fetch-source-details'
    );

    const urlField = document.querySelector('#url');
    const statusBox = document.querySelector(
        '#source-fetch-status'
    );

    if (!button || !urlField || !statusBox) {
        return;
    }

    const field = (id) => document.querySelector(`#${id}`);

    const setIfEmpty = (id, value) => {
        const element = field(id);

        if (!element || value === null || value === undefined) {
            return;
        }

        const formattedValue = Array.isArray(value)
            ? value.join(', ')
            : String(value);

        if (element.value.trim() === '') {
            element.value = formattedValue;
        }
    };

    const setValue = (id, value) => {
        const element = field(id);

        if (!element || value === null || value === undefined) {
            return;
        }

        element.value = Array.isArray(value)
            ? value.join(', ')
            : String(value);
    };

    button.addEventListener('click', async () => {
        const url = urlField.value.trim();

        if (url === '') {
            statusBox.textContent =
                'Paste a URL before fetching details.';
            return;
        }

        button.disabled = true;
        statusBox.textContent = 'Fetching source details…';

        try {
            const body = new URLSearchParams({
                url,
                csrf_token: button.dataset.csrfToken,
            });

            const response = await fetch(
                '/admin/sources/fetch-details.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body,
                }
            );

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(
                    payload.message
                    || 'The source could not be fetched.'
                );
            }

            const data = payload.data;

            setValue('url', data.url);
            setIfEmpty('title', data.title);
            setIfEmpty('organization', data.organization);
            setIfEmpty(
                'organization_type',
                data.organization_type
            );
            setIfEmpty('source_type', data.source_type);
            setIfEmpty(
                'publication_version',
                data.publication_version
            );
            setValue('date_checked', data.date_checked);
            setIfEmpty(
                'public_summary',
                data.public_summary
            );

            setIfEmpty('states', data.states);
            setIfEmpty('counties', data.counties);
            setIfEmpty('fairs', data.fairs);
            setIfEmpty('clubs', data.clubs);
            setIfEmpty('projects', data.projects);
            setIfEmpty('topics', data.topics);
            setIfEmpty('age_groups', data.age_groups);
            setIfEmpty('tags', data.tags);

            const warnings = Array.isArray(data.warnings)
                ? data.warnings.filter(Boolean)
                : [];

            statusBox.textContent = warnings.length > 0
                ? `Details fetched. ${warnings.join(' ')}`
                : 'Details fetched. Review the suggestions before saving.';
        } catch (error) {
            statusBox.textContent = error instanceof Error
                ? error.message
                : 'The source could not be fetched.';
        } finally {
            button.disabled = false;
        }
    });
});