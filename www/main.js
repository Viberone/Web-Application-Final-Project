// ví dụ sử dụng javascript thuần
window.addEventListener("load", () => {
	let title = document.querySelector("h3");

	title.onmouseover = () => {
		title.style.color = "deeppink";
	};

	title.addEventListener("mouseleave", () => {
		title.style.color = "black";
	});
});

// ví dụ sử dụng jquery
$(document).ready(() => {
	$("#test").on("click", () => {
		$("h3").html("jQuery đã hoạt động");
	});
});

// ---------- Note page: auto-save ----------
$(document).ready(function () {
	var $form = $("#note-form");
	if ($form.length === 0) return; // not on the note page

	var noteId = $form.data("note-id") || ""; // empty = note not created yet
	var $status = $("#save-status");
	var timer = null;
	var saving = false;
	var pending = false;

	function save() {
		if (saving) {
			pending = true; // save again as soon as the current one finishes
			return;
		}
		saving = true;
		pending = false;
		$status.text("Saving...");

		$.post("/api/save-note.php", {
			id: noteId,
			title: $("#title").val(),
			content: $("#content").val(),
		})
			.done(function (res) {
				if (!noteId && res.id) {
					noteId = res.id;
					// the URL now points to the note we just created
					history.replaceState(null, "", "/note.php?id=" + noteId);
					$("#delete-btn").data("note-id", noteId).removeClass("d-none");
					$form.data("note-id", noteId); // the label checkboxes read the id from here
					$(".note-label-check").prop("disabled", false);
					$("#label-hint").addClass("d-none");
				}
				$status.text("Saved");
			})
			.fail(function (xhr) {
				if (xhr.status === 401) {
					window.location.href = "/login.php";
				} else {
					$status.text("Save failed");
				}
			})
			.always(function () {
				saving = false;
				if (pending) save();
			});
	}

	$form.on("input", "input, textarea", function () {
		$status.text("Typing...");
		clearTimeout(timer);
		timer = setTimeout(save, 500); // wait 500ms after the last keystroke
	});

	$form.on("submit", function (e) {
		e.preventDefault(); // pressing Enter in the title must not reload the page
	});
});

$(document).ready(function () {
	var $modal = $("#delete-modal");
	if ($modal.length === 0) return;

	var deleteId = null;

	// Works for the buttons on the homepage cards and on the note page
	$(document).on("click", ".btn-delete-note", function () {
		deleteId = $(this).data("note-id");
		$("#delete-error").addClass("d-none");
		$modal.modal("show");
	});

	$("#confirm-delete").on("click", function () {
		var $btn = $(this).prop("disabled", true);

		$.post("/api/delete-note.php", { id: deleteId })
			.done(function () {
				window.location.href = "/";
			})
			.fail(function (xhr) {
				if (xhr.status === 401) {
					window.location.href = "/login.php";
				} else {
					$("#delete-error").removeClass("d-none");
					$btn.prop("disabled", false);
				}
			});
	});
});

// ---------- Notes view: grid / list ----------
$(document).ready(function () {
	var $area = $("#notes-area");
	if ($area.length === 0) return; // not on the homepage

	function setView(view) {
		var isList = view === "list";
		$area.toggleClass("list-view", isList);
		$("#view-grid").toggleClass("active", !isList).attr("aria-pressed", !isList);
		$("#view-list").toggleClass("active", isList).attr("aria-pressed", isList);
		try {
			localStorage.setItem("notesView", view);
		} catch (e) {}
	}

	var saved = "grid"; // default is grid
	try {
		saved = localStorage.getItem("notesView") || "grid";
	} catch (e) {}
	setView(saved);

	$("#view-grid").on("click", function () {
		setView("grid");
	});
	$("#view-list").on("click", function () {
		setView("list");
	});
});

// ---------- Pin / unpin note ----------
$(document).on("click", ".btn-pin-note", function () {
	var $btn = $(this).prop("disabled", true);
	var newState = $btn.data("pinned") ? 0 : 1; // flip the current state

	$.post("/api/pin-note.php", { id: $btn.data("note-id"), pinned: newState })
		.done(function () {
			window.location.reload(); // the order changed, so reload the list
		})
		.fail(function (xhr) {
			if (xhr.status === 401) {
				window.location.href = "/login.php";
			} else {
				alert("Could not pin the note. Please try again.");
				$btn.prop("disabled", false);
			}
		});
});

// ---------- Live search + label filter ----------
$(document).ready(function () {
	var $search = $("#search");
	if ($search.length === 0) return;

	var timer = null;
	var request = null;

	// ids of the label buttons that are switched on
	function selectedLabels() {
		return $(".label-filter.active")
			.map(function () {
				return $(this).data("label-id");
			})
			.get();
	}

	function runSearch() {
		if (request) request.abort(); // ignore an older, slower request

		request = $.get("/", {
			q: $search.val().trim(),
			labels: selectedLabels().join(","),
		})
			.done(function (html) {
				// Parse the returned page and take only its notes area.
				// DOMParser does not run the page's scripts.
				var doc = new DOMParser().parseFromString(html, "text/html");
				var area = doc.getElementById("notes-area");
				if (area) {
					$("#notes-area").html(area.innerHTML);
				} else {
					window.location.href = "/"; // session expired: go through login
				}
			})
			.fail(function (xhr, status) {
				if (status === "abort") return;
				alert("Search failed. Please try again.");
			});
	}

	// Typing: wait 300ms after the last keystroke
	$search.on("input", function () {
		clearTimeout(timer);
		timer = setTimeout(runSearch, 300);
	});

	// Clicking a label button: switch it on/off and filter right away
	$(document).on("click", ".label-filter", function () {
		var $btn = $(this).toggleClass("active");
		$btn.attr("aria-pressed", $btn.hasClass("active"));
		clearTimeout(timer);
		runSearch();
	});
});

// ---------- Labels page ----------
$(document).ready(function () {
	var $addForm = $("#add-label-form");
	if ($addForm.length === 0) return; // not on the labels page

	var $alert = $("#label-alert");
	var $modal = $("#label-delete-modal");
	var deleteId = null;

	function showError(message) {
		$alert.text(message).removeClass("d-none");
	}

	// Sends one request to the labels endpoint
	function callApi(data, onDone, onFail) {
		$alert.addClass("d-none");
		$.post("/api/labels.php", data)
			.done(onDone)
			.fail(function (xhr) {
				if (xhr.status === 401) {
					window.location.href = "/login.php";
					return;
				}
				if (onFail) onFail();
				showError((xhr.responseJSON && xhr.responseJSON.error) || "Something went wrong. Please try again.");
			});
	}

	function reload() {
		window.location.reload();
	}

	// Add
	$addForm.on("submit", function (e) {
		e.preventDefault();
		callApi({ action: "add", name: $("#new-label").val() }, reload);
	});

	// Rename (button, or Enter inside the name field)
	$(document).on("click", ".btn-rename-label", function () {
		var $row = $(this).closest(".label-row");
		callApi({ action: "rename", id: $row.data("label-id"), name: $row.find(".label-name").val() }, reload);
	});
	$(document).on("keydown", ".label-name", function (e) {
		if (e.key === "Enter") {
			e.preventDefault();
			$(this).closest(".label-row").find(".btn-rename-label").click();
		}
	});

	// Delete: first ask for confirmation
	$(document).on("click", ".btn-delete-label", function () {
		var $row = $(this).closest(".label-row");
		deleteId = $row.data("label-id");
		$("#label-delete-name").text($row.find(".label-name").val());
		$modal.modal("show");
	});

	$("#confirm-label-delete").on("click", function () {
		var $btn = $(this).prop("disabled", true);
		callApi(
			{ action: "delete", id: deleteId },
			reload,
			function () {
				$btn.prop("disabled", false);
				$modal.modal("hide");
			}
		);
	});
});

// ---------- Note page: attach / detach labels ----------
$(document).on("change", ".note-label-check", function () {
	var $box = $(this);
	var noteId = $("#note-form").data("note-id");
	var wanted = $box.prop("checked");

	if (!noteId) {
		$box.prop("checked", false); // note not saved yet
		return;
	}

	$box.prop("disabled", true);

	$.post("/api/note-label.php", {
		note_id: noteId,
		label_id: $box.val(),
		attached: wanted ? 1 : 0,
	})
		.fail(function (xhr) {
			$box.prop("checked", !wanted); // undo the tick
			if (xhr.status === 401) {
				window.location.href = "/login.php";
			} else {
				alert("Could not update the label. Please try again.");
			}
		})
		.always(function () {
			$box.prop("disabled", false);
		});
});