document.addEventListener("DOMContentLoaded", () => {
  const fallbackIcons = {
    eye: '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1.3 1.3 0 0 1 0-.696C3.235 7.584 7.247 5 12 5s8.765 2.584 9.938 6.652a1.3 1.3 0 0 1 0 .696C20.765 16.416 16.753 19 12 19S3.235 16.416 2.062 12.348"/><circle cx="12" cy="12" r="3"/></svg>',
    "eye-off": '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 2 20 20"/><path d="M6.71 6.71C4.93 7.89 3.44 9.58 2.62 11.65a1.3 1.3 0 0 0 0 .7C3.79 15.32 7.72 18 12 18c1.1 0 2.15-.18 3.12-.5"/><path d="M10.73 5.08A10.7 10.7 0 0 1 12 5c4.75 0 8.76 2.58 9.94 6.65a1.3 1.3 0 0 1 0 .7 11.4 11.4 0 0 1-3.05 4.6"/><path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"/></svg>',
  };
  const setToggleIcon = (toggle, iconName) => {
    toggle.innerHTML = window.Icons
      ? window.Icons.get(iconName, { size: 17, strokeWidth: 2 })
      : fallbackIcons[iconName];
  };

  document.querySelectorAll("[data-password-strength]").forEach((input) => {
    const meter = input.closest("form").querySelector(".password-strength");
    const toggle = input.parentElement.querySelector(".password-toggle");
    setToggleIcon(toggle, "eye");
    const update = () => {
      const password = input.value;
      const checks = [
        password.length >= 10,
        /[a-z]/.test(password),
        /[A-Z]/.test(password),
        /\d/.test(password),
        /[^a-zA-Z\d]/.test(password),
      ];
      const score = checks.filter(Boolean).length;
      const labels = [
        "Enter a password",
        "Weak password",
        "Fair password",
        "Good password",
        "Almost secure, add the missing requirement",
        "Secure password",
      ];
      meter.dataset.strength = password ? String(score) : "0";
      meter.querySelector("p").textContent = labels[password ? score : 0];
    };
    input.addEventListener("input", update);

    toggle.addEventListener("click", () => {
      const showing = input.type === "text";
      input.type = showing ? "password" : "text";
      setToggleIcon(toggle, showing ? "eye" : "eye-off");
      toggle.setAttribute(
        "aria-label",
        showing ? "Show password" : "Hide password",
      );
      toggle.setAttribute("title", showing ? "Show password" : "Hide password");
    });
  });
});
