$(document).ready(function () {
    var token = localStorage.getItem('session_token');

    if (!token) {
        alert("You are not logged in!");
        window.location.href = 'login.html';
        return;
    }

    // Load Profile Data
    $.ajax({
        url: 'php/profile.php',
        type: 'POST',
        data: { token: token },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                var data = response.data;
                $('#pName').text(data.name);
                $('#pEmail').text(data.email);
                $('#pAge').text(data.age);
                $('#pDob').text(data.dob);
                $('#pContact').text(data.contact);
            } else {
                alert('Session expired or invalid: ' + response.message);
                localStorage.removeItem('session_token');
                window.location.href = 'login.html';
            }
        },
            error: function (xhr, status, error) {
                console.error("Raw response:", xhr.responseText);
                var msg = "An error occurred during login.";
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.message) msg = resp.message;
                } catch (e) {
                    msg = "Server Debug: " + xhr.responseText.substring(0, 100);
                }
                alert("Error: " + msg);
            }
    });

    // Logout
    $('#logoutBtn').click(function () {
        localStorage.removeItem('session_token');
        window.location.href = 'login.html';
    });
});
