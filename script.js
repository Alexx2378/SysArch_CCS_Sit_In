document.addEventListener("DOMContentLoaded", function () {
  const pages = document.querySelectorAll(".page");
  const navLinks = document.querySelectorAll(".nav-links a");

  function showPage(pageId, clickedLink = null) {
    pages.forEach((page) => {
      page.classList.remove("active-page");
    });

    const targetPage = document.getElementById(pageId);
    if (targetPage) {
      targetPage.classList.add("active-page");
    }

    navLinks.forEach((link) => {
      link.classList.remove("active-nav");
    });

    if (clickedLink) {
      clickedLink.classList.add("active-nav");
    }
  }

  const homeLink = document.getElementById("home-link");
  const editProfileLink = document.getElementById("edit-profile-link");
  const notificationLink = document.getElementById("notification-link");
  const historyLink = document.getElementById("history-link");
  const reservationLink = document.getElementById("reservation-link");
  const heroEditLink = document.getElementById("hero-edit-link");
  const heroReservationLink = document.getElementById("hero-reservation-link");
  const linkByPageId = {
    "home-page": homeLink,
    "edit-profile-page": editProfileLink,
    "notification-page": notificationLink,
    "history-page": historyLink,
    "reservation-page": reservationLink,
  };

  if (homeLink) {
    homeLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("home-page", this);
    });
  }

  if (editProfileLink) {
    editProfileLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("edit-profile-page", this);
    });
  }

  if (notificationLink) {
    notificationLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("notification-page", this);
    });
  }

  if (historyLink) {
    historyLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("history-page", this);
    });
  }

  if (reservationLink) {
    reservationLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("reservation-page", this);
    });
  }

  if (heroEditLink && editProfileLink) {
    heroEditLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("edit-profile-page", editProfileLink);
    });
  }

  if (heroReservationLink && reservationLink) {
    heroReservationLink.addEventListener("click", function (e) {
      e.preventDefault();
      showPage("reservation-page", reservationLink);
    });
  }

  const initialPage = document.body.getAttribute("data-page");
  if (initialPage && linkByPageId[initialPage]) {
    showPage(initialPage, linkByPageId[initialPage]);
  }

  const profileImageInput = document.getElementById("profile-image");
  const cameraImageDataInput = document.getElementById("camera-image-data");
  const profilePreview = document.getElementById("profile-preview");
  const homeStudentProfileImage = document.getElementById("home-student-profile-img");
  const quickCameraBtn = document.getElementById("quick-camera-btn");
  const cameraPreview = document.getElementById("camera-preview");
  const cameraCanvas = document.getElementById("camera-canvas");
  const startCameraBtn = document.getElementById("start-camera-btn");
  const captureCameraBtn = document.getElementById("capture-camera-btn");
  const stopCameraBtn = document.getElementById("stop-camera-btn");
  let cameraStream = null;

  function syncProfileImages(src) {
    if (!src) {
      return;
    }

    if (profilePreview) {
      profilePreview.src = src;
    }

    if (homeStudentProfileImage) {
      homeStudentProfileImage.src = src;
    }
  }

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach((track) => track.stop());
      cameraStream = null;
    }

    if (cameraPreview) {
      cameraPreview.srcObject = null;
    }
  }

  profileImageInput?.addEventListener("change", function () {
    if (cameraImageDataInput) {
      cameraImageDataInput.value = "";
    }

    const file = this.files && this.files[0] ? this.files[0] : null;
    if (!file || !profilePreview) {
      return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
      if (event.target?.result) {
        syncProfileImages(String(event.target.result));
      }
    };
    reader.readAsDataURL(file);
  });

  profilePreview?.addEventListener("click", function () {
    profileImageInput?.click();
  });

  quickCameraBtn?.addEventListener("click", function () {
    startCameraBtn?.click();
  });

  startCameraBtn?.addEventListener("click", async function () {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !cameraPreview) {
      alert("Camera is not supported on this device/browser.");
      return;
    }

    try {
      stopCamera();
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user" },
        audio: false,
      });
      cameraPreview.srcObject = cameraStream;
      await cameraPreview.play();
    } catch (error) {
      alert("Unable to access camera. Please allow camera permission.");
    }
  });

  captureCameraBtn?.addEventListener("click", function () {
    if (!cameraPreview || !cameraCanvas || !profileImageInput) {
      return;
    }

    if (!cameraPreview.videoWidth || !cameraPreview.videoHeight) {
      alert("Start camera first before capturing.");
      return;
    }

    cameraCanvas.width = cameraPreview.videoWidth;
    cameraCanvas.height = cameraPreview.videoHeight;

    const ctx = cameraCanvas.getContext("2d");
    if (!ctx) {
      return;
    }

    ctx.drawImage(cameraPreview, 0, 0, cameraCanvas.width, cameraCanvas.height);

    cameraCanvas.toBlob(function (blob) {
      if (!blob) {
        return;
      }

      const file = new File([blob], "camera-capture.png", { type: "image/png" });
      const dt = new DataTransfer();
      dt.items.add(file);
      profileImageInput.files = dt.files;

      syncProfileImages(URL.createObjectURL(blob));

      if (cameraImageDataInput) {
        cameraImageDataInput.value = cameraCanvas.toDataURL("image/png");
      }
    }, "image/png");
  });

  stopCameraBtn?.addEventListener("click", function () {
    stopCamera();
  });

  const historySearchInput = document.getElementById("history-search");
  const historyNoResultsRow = document.getElementById("history-no-results");

  historySearchInput?.addEventListener("input", function () {
    const keyword = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#history-table-body tr[data-history-row="1"]');
    let visibleCount = 0;

    rows.forEach((row) => {
      const rowText = row.textContent ? row.textContent.toLowerCase() : "";
      const matched = keyword === "" || rowText.includes(keyword);
      row.style.display = matched ? "table-row" : "none";
      if (matched) {
        visibleCount += 1;
      }
    });

    if (historyNoResultsRow) {
      historyNoResultsRow.style.display = visibleCount === 0 ? "table-row" : "none";
    }
  });

  window.addEventListener("beforeunload", stopCamera);
});