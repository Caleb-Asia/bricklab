/**
 * BrickLab The Grid & Buildmate Interactions (Build Logs, Likes, Buildmates)
 */
document.addEventListener("DOMContentLoaded", () => {
    // ------------------------------------------
    // AJAX Like / Unlike on Build Logs
    // ------------------------------------------
    document.querySelectorAll("[data-like-form]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const btn = form.querySelector(".community-like-btn");
            const countEl = form.querySelector("[data-like-count]");
            const iconEl = form.querySelector(".community-like-icon");
            if (!btn || !countEl) return;

            const isCurrentlyLiked = btn.dataset.liked === "1";
            const currentCount = parseInt(countEl.textContent, 10) || 0;

            // Optimistic update
            const nextLiked = !isCurrentlyLiked;
            btn.dataset.liked = nextLiked ? "1" : "0";
            btn.classList.toggle("is-liked", nextLiked);
            btn.setAttribute("aria-label", nextLiked ? "Unlike" : "Like");
            if (iconEl) iconEl.textContent = nextLiked ? "♥" : "♡";
            countEl.textContent = Math.max(0, currentCount + (nextLiked ? 1 : -1));

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    if (typeof data.liked === "boolean") {
                        btn.dataset.liked = data.liked ? "1" : "0";
                        btn.classList.toggle("is-liked", data.liked);
                        if (iconEl) iconEl.textContent = data.liked ? "♥" : "♡";
                    }
                    if (typeof data.count === "number") {
                        countEl.textContent = data.count;
                    }
                } else {
                    // Revert
                    btn.dataset.liked = isCurrentlyLiked ? "1" : "0";
                    btn.classList.toggle("is-liked", isCurrentlyLiked);
                    if (iconEl) iconEl.textContent = isCurrentlyLiked ? "♥" : "♡";
                    countEl.textContent = currentCount;
                }
            } catch (err) {
                console.error("BrickLab like request failed:", err);
                btn.dataset.liked = isCurrentlyLiked ? "1" : "0";
                btn.classList.toggle("is-liked", isCurrentlyLiked);
                if (iconEl) iconEl.textContent = isCurrentlyLiked ? "♥" : "♡";
                countEl.textContent = currentCount;
            }
        });
    });

    // ------------------------------------------
    // AJAX Buildmate Connections (Request, Accept, Disconnect)
    // ------------------------------------------
    document.querySelectorAll("[data-buildmate-form]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const btn = form.querySelector("button");
            const countEl = document.querySelector("[data-buildmate-count]");
            if (!btn) return;

            const prevStatus = btn.dataset.buildmateStatus || "";
            const prevText = btn.textContent.trim();
            const prevClass = btn.className;

            // Optimistic button state
            let nextText = "Request Pending";
            let nextClass = "btn btn-secondary";
            let nextStatus = "sent";

            if (prevStatus === "received") {
                nextText = "Buildmates ✓";
                nextStatus = "accepted";
            } else if (prevStatus === "sent" || prevStatus === "accepted") {
                nextText = "Add Buildmate";
                nextClass = "btn btn-primary";
                nextStatus = "";
            }

            btn.textContent = nextText;
            btn.className = nextClass;
            btn.dataset.buildmateStatus = nextStatus;

            try {
                const formData = new FormData(form);
                if (event.submitter && event.submitter.name) {
                    formData.set(event.submitter.name, event.submitter.value);
                }
                const response = await fetch(form.action || window.location.href, {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status) {
                        btn.dataset.buildmateStatus = data.status === "none" ? "" : data.status;
                        if (data.status === "accepted") {
                            btn.textContent = "Buildmates ✓";
                            btn.className = "btn btn-secondary";
                        } else if (data.status === "sent") {
                            btn.textContent = "Request Pending";
                            btn.className = "btn btn-secondary";
                        } else if (data.status === "received") {
                            btn.textContent = "Accept Buildmate";
                            btn.className = "btn btn-primary";
                        } else {
                            btn.textContent = "Add Buildmate";
                            btn.className = "btn btn-primary";
                        }
                    }
                    if (countEl && typeof data.buildmates_count === "number") {
                        countEl.textContent = data.buildmates_count;
                    }
                } else {
                    // Revert
                    btn.textContent = prevText;
                    btn.className = prevClass;
                    btn.dataset.buildmateStatus = prevStatus;
                }
            } catch (err) {
                console.error("BrickLab buildmate request failed:", err);
                btn.textContent = prevText;
                btn.className = prevClass;
                btn.dataset.buildmateStatus = prevStatus;
            }
        });
    });

    // ------------------------------------------
    // Auto-expanding composer textarea
    // ------------------------------------------
    const composeTextarea = document.querySelector(".community-compose-form textarea");
    if (composeTextarea) {
        composeTextarea.addEventListener("input", () => {
            composeTextarea.style.height = "auto";
            composeTextarea.style.height = Math.max(90, composeTextarea.scrollHeight) + "px";
        });
    }
});
