<?php
session_start();

// Allow only guest access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest') {
  header("Location: login.php");
  exit();
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = $_POST["email"];
  $test = $_POST["test"];
  $score = $_POST["score"];
  $percentage = $_POST["percentage"];
  $date = date("Y-m-d");

  $conn = new mysqli("localhost", "root", "", "quiz_db");
  if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

  // Prevent re-submission
  $check = $conn->prepare("SELECT * FROM quiz_results WHERE email=? AND test_name=?");
  $check->bind_param("ss", $email, $test);
  $check->execute();
  $res = $check->get_result();

  if ($res->num_rows == 0) {
    $insert = $conn->prepare("INSERT INTO quiz_results (email, test_name, score, percentage, submission_date) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("ssdds", $email, $test, $score, $percentage, $date);
    $insert->execute();
    $message = "✅ Quiz submitted successfully.";
  } else {
    $message = "⚠️ You have already submitted $test.";
  }

  $check->close();
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Guest Dashboard - D'vine Healthcare</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    body { font-family: Arial; background: #f4f6f9; padding: 30px; }
    .container {
      max-width: 700px; margin: auto; background: white; padding: 30px;
      border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2, h3 { text-align: center; }
    label { display: block; margin: 10px 0; }
    input, select, button {
      width: 100%; padding: 10px; margin: 10px 0; font-size: 16px;
      border-radius: 5px; border: 1px solid #ccc;
    }
    .question {
      margin: 15px 0; padding: 10px; background: #f9f9f9; border-radius: 5px;
    }
    .message { text-align: center; color: green; font-weight: bold; margin-top: 10px; }
    .error { color: red; }
    button { background: green; color: white; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Welcome Guest - Take Your Quiz</h2>

    <?php if ($message): ?>
      <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" id="quizForm" onsubmit="return calculateScore()">
      <label for="email">Email:</label>
      <input type="email" name="email" id="email" value="<?= $_SESSION['username'] ?>" required readonly>

      <label for="test">Select Test:</label>
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
      questions.forEach((q, i) => {
        const selected = document.querySelector(`input[name="q${i}"]:checked`);
        if (selected && selected.value === q.answer) score++;
      });
      const percentage = ((score / questions.length) * 100).toFixed(2);
      document.getElementById("scoreInput").value = score;
      document.getElementById("percentageInput").value = percentage;
      return true;
    }

    window.addEventListener("DOMContentLoaded", loadQuestions);
  </script>
</body>
</html>

