function displayHiddenContent (payment) {
  document.getElementById("my-hidden-content").innerHTML = "<h3>Erfolgreich bezahlt.</h3>";
  document.getElementsByClassName("money-button")[0].removeAttribute("style");
  document.getElementsByClassName("money-button")[0].style.display = "none";
}
