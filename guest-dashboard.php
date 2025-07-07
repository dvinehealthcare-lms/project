<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest') {
  header("Location: login.php");
  exit();
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = $_SESSION['email'];
  $test = $_POST["test"];
  $score = $_POST["score"];
  $percentage = $_POST["percentage"];
  $date = date("Y-m-d");

  $conn = new mysqli("localhost", "root", "", "dvine_db");
  if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

  // Check if user exists
  $verifyUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
  $verifyUser->bind_param("s", $email);
  $verifyUser->execute();
  $verifyUser->store_result();

  if ($verifyUser->num_rows === 0) {
    $message = "❌ User not found. Please contact the admin.";
    $verifyUser->close();
  } else {
    $verifyUser->close();

    // Check if row exists
    $check = $conn->prepare("SELECT * FROM quiz_results WHERE user_email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $res = $check->get_result();
    $exists = $res->num_rows > 0;
    $res->close();

    // Column names based on test
    $scoreCol = $test . "_score";
    $percentCol = $test . "_percentage";
    $dateCol = $test . "_date";

    // Check if that test is already submitted
    if ($exists) {
      $submittedCheck = $conn->prepare("SELECT $scoreCol FROM quiz_results WHERE user_email=?");
      $submittedCheck->bind_param("s", $email);
      $submittedCheck->execute();
      $submittedCheck->bind_result($existingScore);
      $submittedCheck->fetch();
      $submittedCheck->close();

      if ($existingScore !== null) {
        $message = "⚠️ You have already submitted $test.";
      } else {
        $update = $conn->prepare("UPDATE quiz_results SET $scoreCol=?, $percentCol=?, $dateCol=? WHERE user_email=?");
        $update->bind_param("ddss", $score, $percentage, $date, $email);
        $update->execute();
        $message = "✅ $test submitted successfully.";
      }
    } else {
      $insert = $conn->prepare("INSERT INTO quiz_results (user_email, $scoreCol, $percentCol, $dateCol) VALUES (?, ?, ?, ?)");
      $insert->bind_param("sdds", $email, $score, $percentage, $date);
      $insert->execute();
      $message = "✅ $test submitted successfully.";
    }
  }

  $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Guest Quiz - D'vine Healthcare</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: Arial; background: #f4f6f9; padding: 30px; }
    .container {
      max-width: 700px; margin: auto; background: white; padding: 30px;
      border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { text-align: center; }
    label { display: block; margin: 10px 0; }
    select, button {
      width: 100%; padding: 10px; font-size: 16px;
      border-radius: 5px; border: 1px solid #ccc; margin-bottom: 15px;
    }
    .question {
      background: #f9f9f9; padding: 10px; border-radius: 5px;
      margin-bottom: 15px;
    }
    .message { text-align: center; font-weight: bold; margin-bottom: 15px; }
    .success { color: green; }
    .error { color: red; }
    button { background: green; color: white; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Welcome Guest - Take Quiz</h2>

    <?php if ($message): ?>
      <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form method="post" onsubmit="return calculateScore()">
      <label>Select Test:</label>
      <select name="test" id="test" onchange="loadQuestions()" required>
        <option value="test1">Test 1</option>
        <option value="test2">Test 2</option>
      </select>

      <div id="questionsContainer"></div>

      <input type="hidden" name="score" id="scoreInput" />
      <input type="hidden" name="percentage" id="percentageInput" />

      <button type="submit">Submit Quiz</button>
    </form>
  </div>

  <script>
    const quizData = {
      test1: [
        { q: "Capital of India?", options: ["Delhi", "Mumbai", "Kolkata"], answer: "Delhi" },
        { q: "2 + 2 = ?", options: ["3", "4", "5"], answer: "4" },
        { q: "HTML stands for?", options: ["Hot Mail", "Hyper Text Markup Language", "HighText Machine Language"], answer: "Hyper Text Markup Language" }
      ],
      test2: [
        { q: "Red planet?", options: ["Earth", "Mars", "Venus"], answer: "Mars" },
        { q: "CSS means?", options: ["Cascading Style Sheets", "Creative Style Syntax", "Computer Style Settings"], answer: "Cascading Style Sheets" },
        { q: "3 x 3 = ?", options: ["6", "9", "12"], answer: "9" }
      ]
    };

    function loadQuestions() {
      const test = document.getElementById("test").value;
      const container = document.getElementById("questionsContainer");
      container.innerHTML = "";
      quizData[test].forEach((q, i) => {
        const div = document.createElement("div");
        div.className = "question";
        div.innerHTML = `<p><strong>${i + 1}. ${q.q}</strong></p>` + q.options.map(opt => `
          <label><input type="radio" name="q${i}" value="${opt}"> ${opt}</label>
        `).join("");
        container.appendChild(div);
      });
    }

    function calculateScore() {
      const test = document.getElementById("test").value;
      const questions = quizData[test];
      let score = 0;
      let allAnswered = true;

      questions.forEach((q, i) => {
        const selected = document.querySelector(`input[name="q${i}"]:checked`);
        if (!selected) allAnswered = false;
        else if (selected.value === q.answer) score++;
      });

      if (!allAnswered) {
        alert("❗ Please answer all questions.");
        return false;
      }

      const percentage = ((score / questions.length) * 100).toFixed(2);
      document.getElementById("scoreInput").value = score;
      document.getElementById("percentageInput").value = percentage;
      return true;
    }

    window.addEventListener("DOMContentLoaded", loadQuestions);
  </script>
</body>
</html>




