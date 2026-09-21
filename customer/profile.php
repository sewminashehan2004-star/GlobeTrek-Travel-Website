<?php

include "../includes/customer_auth.php";
include "../includes/db.php";

$basePath = "../";
$activePage = "profile";

$userId = (int) $_SESSION["user_id"];

$message = "";
$messageType = "";

/*
    Get customer profile
*/

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        location,
        bio,
        profile_image
    FROM users
    WHERE id = ?
      AND role = 'customer'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Unable to load your profile.");
}

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}


/*
    Update profile
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $bio = trim($_POST["bio"] ?? "");

    $profileImage = $user["profile_image"] ?? "";

    if ($name === "") {

        $message = "Please enter your name.";
        $messageType = "error";

    } elseif (strlen($name) > 100) {

        $message = "Name is too long.";
        $messageType = "error";

    } elseif (
        $phone !== ""
        &&
        !preg_match("/^[0-9+()\\-\\s]{7,20}$/", $phone)
    ) {

        $message = "Please enter a valid phone number.";
        $messageType = "error";

    } elseif (strlen($location) > 100) {

        $message = "Location is too long.";
        $messageType = "error";

    } elseif (strlen($bio) > 500) {

        $message = "Bio must be 500 characters or less.";
        $messageType = "error";

    }


    /*
        Profile image upload
    */

    if (
        $message === ""
        &&
        isset($_FILES["profile_image"])
        &&
        $_FILES["profile_image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["profile_image"]["error"] !== UPLOAD_ERR_OK) {

            $message = "There was a problem uploading the image.";
            $messageType = "error";

        } elseif ($_FILES["profile_image"]["size"] > 3 * 1024 * 1024) {

            $message = "Profile image must be smaller than 3 MB.";
            $messageType = "error";

        } else {

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            $fileType = mime_content_type(
                $_FILES["profile_image"]["tmp_name"]
            );

            if (!isset($allowedTypes[$fileType])) {

                $message = "Only JPG, PNG and WEBP images are allowed.";
                $messageType = "error";

            } else {

                $uploadDir = "../uploads/profile/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = $allowedTypes[$fileType];

                $newFileName =
                    "user_"
                    . $userId
                    . "_"
                    . time()
                    . "."
                    . $extension;

                $uploadPath =
                    $uploadDir
                    . $newFileName;

                if (
                    move_uploaded_file(
                        $_FILES["profile_image"]["tmp_name"],
                        $uploadPath
                    )
                ) {

                    if (!empty($profileImage)) {

                        $oldImage =
                            $uploadDir
                            . basename($profileImage);

                        if (file_exists($oldImage)) {
                            unlink($oldImage);
                        }

                    }

                    $profileImage = $newFileName;

                } else {

                    $message = "Could not save the profile image.";
                    $messageType = "error";

                }

            }

        }

    }


    /*
        Save changes
    */

    if ($message === "") {

        $updateSql = "
            UPDATE users
            SET
                name = ?,
                phone = ?,
                location = ?,
                bio = ?,
                profile_image = ?
            WHERE id = ?
              AND role = 'customer'
        ";

        $updateStmt = mysqli_prepare(
            $conn,
            $updateSql
        );

        if (!$updateStmt) {

            $message = "Unable to update your profile.";
            $messageType = "error";

        } else {

            mysqli_stmt_bind_param(
                $updateStmt,
                "sssssi",
                $name,
                $phone,
                $location,
                $bio,
                $profileImage,
                $userId
            );

            if (mysqli_stmt_execute($updateStmt)) {

                $_SESSION["name"] = $name;
                $_SESSION["profile_image"] = $profileImage;

                $user["name"] = $name;
                $user["phone"] = $phone;
                $user["location"] = $location;
                $user["bio"] = $bio;
                $user["profile_image"] = $profileImage;

                $message = "Your profile has been updated successfully.";
                $messageType = "success";

            } else {

                $message = "Unable to update your profile.";
                $messageType = "error";

            }

            mysqli_stmt_close($updateStmt);

        }

    }

}

$profileImageUrl = "";

if (!empty($user["profile_image"])) {
    $profileImageUrl =
        "../uploads/profile/"
        . basename($user["profile_image"]);
}

$avatarLetter =
    strtoupper(
        substr(
            $user["name"],
            0,
            1
        )
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        My Profile | GlobeTrek
    </title>

    <link
        rel="stylesheet"
        href="../css/site.css?v=30">

    <link
        rel="stylesheet"
        href="../css/profile.css?v=30">

</head>


<body>


<?php include "../includes/navbar.php"; ?>


<main class="profile-page">

    <div class="profile-wrapper">

        <div class="page-header">

            <span>
                YOUR ACCOUNT
            </span>

            <h1>
                My Profile
            </h1>

            <p>
                Keep your contact details and profile information up to date.
            </p>

        </div>


        <?php if ($message !== "") { ?>

            <div class="message <?php echo $messageType === "success" ? "success-message" : "error-message"; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php } ?>


        <div class="profile-layout">


            <aside class="profile-sidebar">

                <div class="profile-photo-container">

                    <?php if ($profileImageUrl !== "") { ?>

                        <img
                            id="profilePreview"
                            src="<?php echo htmlspecialchars($profileImageUrl); ?>"
                            alt="Profile image">

                    <?php } else { ?>

                        <div
                            id="profilePreview"
                            class="profile-placeholder">
                            <?php echo htmlspecialchars($avatarLetter); ?>
                        </div>

                    <?php } ?>


                    <label
                        for="profile_image"
                        class="camera-button"
                        title="Choose profile photo">

                        📷

                    </label>

                </div>


                <h2>
                    <?php echo htmlspecialchars($user["name"]); ?>
                </h2>

                <p>
                    <?php echo htmlspecialchars($user["email"]); ?>
                </p>

                <span class="customer-badge">
                    CUSTOMER
                </span>

            </aside>


            <section class="profile-form-card">

                <div class="form-heading">

                    <span>
                        PERSONAL INFORMATION
                    </span>

                    <h2>
                        Profile details
                    </h2>

                    <p>
                        Your email address is used for account login and cannot be changed here.
                    </p>

                </div>


                <form
                    method="POST"
                    enctype="multipart/form-data">


                    <div class="form-grid">

                        <div class="form-group">

                            <label for="name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                maxlength="100"
                                value="<?php echo htmlspecialchars($user["name"]); ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                value="<?php echo htmlspecialchars($user["email"]); ?>"
                                disabled>

                        </div>


                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                maxlength="20"
                                value="<?php echo htmlspecialchars($user["phone"] ?? ""); ?>"
                                placeholder="+94 77 123 4567">

                        </div>


                        <div class="form-group">

                            <label for="location">
                                Location
                            </label>

                            <input
                                type="text"
                                id="location"
                                name="location"
                                maxlength="100"
                                value="<?php echo htmlspecialchars($user["location"] ?? ""); ?>"
                                placeholder="Kandy, Sri Lanka">

                        </div>

                    </div>


                    <div class="form-group full-width">

                        <label for="bio">
                            About Me
                        </label>

                        <textarea
                            id="bio"
                            name="bio"
                            maxlength="500"
                            placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($user["bio"] ?? ""); ?></textarea>

                        <span class="character-count">
                            <span id="bioCount">0</span>/500
                        </span>

                    </div>


                    <div class="form-group">

                        <label for="profile_image">
                            Profile Photo
                        </label>

                        <input
                            type="file"
                            id="profile_image"
                            name="profile_image"
                            accept=".jpg,.jpeg,.png,.webp">

                        <small>
                            JPG, PNG or WEBP. Maximum 3 MB.
                        </small>

                    </div>


                    <div class="form-actions">

                        <a
                            href="cdashboard.php"
                            class="cancel-button">
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="save-button">
                            Save Changes
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </div>

</main>


<?php include "../includes/footer.php"; ?>


<script>

const imageInput =
    document.getElementById("profile_image");

const preview =
    document.getElementById("profilePreview");

const bio =
    document.getElementById("bio");

const bioCount =
    document.getElementById("bioCount");


function updateBioCount() {

    if (bio && bioCount) {

        bioCount.textContent =
            bio.value.length;

    }

}

updateBioCount();


if (bio) {

    bio.addEventListener(
        "input",
        updateBioCount
    );

}


if (imageInput && preview) {

    imageInput.addEventListener(
        "change",
        function () {

            const file =
                this.files[0];

            if (!file) {
                return;
            }

            const allowed =
                [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];

            if (!allowed.includes(file.type)) {

                alert(
                    "Please select a JPG, PNG or WEBP image."
                );

                this.value = "";

                return;

            }

            const reader =
                new FileReader();

            reader.onload =
                function (event) {

                    if (preview.tagName === "IMG") {

                        preview.src =
                            event.target.result;

                    } else {

                        const img =
                            document.createElement("img");

                        img.id =
                            "profilePreview";

                        img.alt =
                            "Profile image";

                        img.src =
                            event.target.result;

                        preview.replaceWith(
                            img
                        );

                    }

                };

            reader.readAsDataURL(file);

        }
    );

}

</script>


</body>
</html>
