window.addEventListener('DOMContentLoaded', event => {
    var checkboxesListItems = [].slice.call(document.querySelectorAll('.checklist li'));
    checkboxesListItems.map(function (checkboxesListItem) {
        checkboxesListItem.addEventListener('click', event => {
            event.preventDefault();
            const mwcCheckbox = checkboxesListItem.querySelector('mwc-checkbox');
            if (mwcCheckbox.hasAttribute('checked')) {
                mwcCheckbox.removeAttribute('checked');
            } else {
                mwcCheckbox.setAttribute('checked', 'checked');
            }
        });
    });
});
