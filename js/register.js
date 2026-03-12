$(document).ready(function () {
    $('#registerForm').on('submit', function (e) {
        e.preventDefault(); // STRICTLY NO FORM SUBMISSION

        var formData = {
            name: $('#name').val(),
            email: $('#email').val(),
            password: $('#password').val(),
            age: $('#age').val(),
            dob: $('#dob').val(),
            contact: $('#contact').val()
        };

        // Basic validation
        if (!formData.name || !formData.email || !formData.password) {
            alert("Please fill all required fields");
            return;
        }

        $.ajax({
            url: 'php/register.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    alert('Registration Successful! Redirecting to login...');
                    window.location.href = 'login.html';
                } else {
                    alert('Registration Failed: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("Raw response:", xhr.responseText);
                var msg = "An error occurred during registration.";

                // Try to parse JSON from responseText even if status is error
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.message) {
                        msg = resp.message;
                    }
                } catch (e) {
                    // If parsing fails, use the status text or a generic message
                    if (xhr.status === 404) msg = "Server file not found (404).";
                    else if (xhr.status === 500) msg = "Internal Server Error (500). Check PHP logs.";
                }

                alert("Error: " + msg);
            }
        });
    });
});
