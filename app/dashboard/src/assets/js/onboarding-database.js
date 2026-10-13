// Storage step of the onboarding: pick SQLite or a database server, then configure it.
(() => {
    const form = document.getElementById('databaseOnboardingForm');
    const choiceStage = document.getElementById('storageChoiceStage');
    const configStage = document.getElementById('storageConfigStage');
    const button = document.getElementById('storageChoiceContinue');
    const storage = form.querySelectorAll('input[name="storage"]');
    const remoteDriver = document.getElementById('remoteDriver');
    const databaseDriver = document.getElementById('databaseDriver');
    const setChoice = () => {
        const remote = [...storage].some((radio) => radio.checked && radio.value === 'remote');
        databaseDriver.value = remote ? remoteDriver.value : 'sqlite';
        button.textContent = remote ? 'Continue to server setup' : 'Continue with SQLite';
        document.querySelectorAll('[data-storage-option]').forEach((option) => option.classList.toggle('is-selected', option.dataset.storageOption === (remote ? 'remote' : 'sqlite')));
    };
    const showStage = (stage) => {
        choiceStage.hidden = stage !== 'choice';
        configStage.hidden = stage !== 'config';
        configStage.querySelectorAll('input, select').forEach((field) => field.disabled = stage !== 'config');
    };
    storage.forEach((radio) => radio.addEventListener('change', setChoice));
    remoteDriver.addEventListener('change', () => databaseDriver.value = remoteDriver.value);
    button.addEventListener('click', () => {
        if (databaseDriver.value === 'sqlite') form.requestSubmit();
        else showStage('config');
    });
    document.getElementById('backToStorageChoice').addEventListener('click', () => showStage('choice'));
    remoteDriver.value = databaseDriver.value === 'mariadb' ? 'mariadb' : 'mysql';
    setChoice();
    showStage(form.dataset.initialStage);
})();
