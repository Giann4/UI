function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}

function toggleCourseField() {
    const roleSelect = document.getElementById("roleSelect");
    const courseField = document.getElementById("courseField");
    const courseSelect = document.getElementById("courseSelect");

    if (!roleSelect || !courseField || !courseSelect) return;

    if (roleSelect.value === "student") {
        courseField.style.display = "block";
        courseSelect.setAttribute("required", "required");
    } else {
        courseField.style.display = "none";
        courseSelect.removeAttribute("required");
        courseSelect.value = "";
    }
}

window.onload = function () {
    toggleCourseField();
};
function toggleDropdown() {
    var content = document.getElementById("dropdownContent");

    if (content.style.display === "block") {
        content.style.display = "none";
    } else {
        content.style.display = "block";
    }
}

window.onclick = function(e) {
    if (!e.target.matches('.dropdown-btn')) {
        var content = document.getElementById("dropdownContent");
        if (content) {
            content.style.display = "none";
        }
    }
};

function openClassModal() {
    document.getElementById("classModal").style.display = "flex";
}

function closeClassModal() {
    document.getElementById("classModal").style.display = "none";
}

/* SA REQUEST STUDENT TO */

function openRequestModal() {
    const modal = document.getElementById("requestModal");
    if (modal) modal.style.display = "flex";
}

function closeRequestModal() {
    const modal = document.getElementById("requestModal");
    if (modal) modal.style.display = "none";
}

function closeSuccessModal() {
    const modal = document.getElementById("successModal");
    if (modal) {
        modal.style.display = "none";
        window.location.href = "student.php";
    }
}
 /* SA STUDENT RESULT TO */
function openRequestModal() {
    const modal = document.getElementById("requestModal");
    if (modal) modal.style.display = "flex";
}

function closeRequestModal() {
    const modal = document.getElementById("requestModal");
    if (modal) modal.style.display = "none";
}

function closeSuccessModal() {
    const modal = document.getElementById("successModal");
    if (modal) {
        modal.style.display = "none";
        window.location.href = "student.php";
    }
}
