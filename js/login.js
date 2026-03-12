$(document).ready(function () {
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        var formData = {
            email: $('#email').val(),
            password: $('#password').val()
        };

        if (!formData.email || !formData.password) {
            alert("Please fill in all fields");
            return;
        }

        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    // Store session token in local storage
                    localStorage.setItem('session_token', response.token);
                    alert('Login Successful! Redirecting to profile...');
                    window.location.href = 'profile.html';
                } else {
                    alert('Login Failed: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                alert('An error occurred during login.');
            }
        });
    });
});
