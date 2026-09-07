function initMap() {
  var map;
  var bounds = new google.maps.LatLngBounds();
  var mapOptions = {
    mapTypeId: 'roadmap',
    mapTypeControl: false,

    // How zoomed in you want the map to start at (always required)
    // zoom: 11,

    // The latitude and longitude to center the map (always required)
    // center: new google.maps.LatLng(40.6700, -73.9400), // New York

    // How you would like to style the map. 
    // This is where you would paste any style found on Snazzy Maps.
    styles: [{ "featureType": "administrative", "elementType": "geometry.fill", "stylers": [{ "visibility": "off" }] }, { "featureType": "administrative", "elementType": "labels.text", "stylers": [{ "visibility": "on" }, { "color": "#8e8e8e" }] }, { "featureType": "administrative", "elementType": "labels.text.fill", "stylers": [{ "color": "#7f7f7f" }] }, { "featureType": "administrative", "elementType": "labels.text.stroke", "stylers": [{ "visibility": "off" }] }, { "featureType": "administrative.country", "elementType": "geometry.stroke", "stylers": [{ "color": "#bebebe" }] }, { "featureType": "administrative.province", "elementType": "geometry.stroke", "stylers": [{ "visibility": "on" }, { "color": "#cbcbcb" }, { "weight": "0.69" }] }, { "featureType": "administrative.locality", "elementType": "geometry", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "landscape", "elementType": "all", "stylers": [{ "color": "#ffffff" }, { "saturation": "0" }] }, { "featureType": "poi.attraction", "elementType": "all", "stylers": [{ "visibility": "on" }, { "color": "#ffffff" }, { "saturation": "0" }] }, { "featureType": "poi.attraction", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.business", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.government", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.medical", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.park", "elementType": "geometry.fill", "stylers": [{ "color": "#efbfb7" }, { "visibility": "on" }] }, { "featureType": "poi.park", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.place_of_worship", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.school", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.sports_complex", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.sports_complex", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.sports_complex", "elementType": "labels.text", "stylers": [{ "visibility": "off" }] }, { "featureType": "road", "elementType": "all", "stylers": [{ "saturation": "-100" }, { "lightness": "50" }, { "gamma": "1" }] }, { "featureType": "road", "elementType": "geometry.fill", "stylers": [{ "color": "#e4e4e4" }, { "saturation": "0" }] }, { "featureType": "road.highway", "elementType": "all", "stylers": [{ "visibility": "simplified" }, { "saturation": "0" }] }, { "featureType": "road.highway", "elementType": "geometry", "stylers": [{ "color": "#f2f2f2" }, { "saturation": "0" }] }, { "featureType": "road.highway", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "road.highway", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "road.arterial", "elementType": "all", "stylers": [{ "saturation": "0" }] }, { "featureType": "road.arterial", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "road.arterial", "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] }, { "featureType": "road.local", "elementType": "all", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [{ "color": "#e4e4e4" }, { "lightness": "0" }, { "gamma": "1" }, { "saturation": "0" }] }, { "featureType": "road.local", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "transit", "elementType": "all", "stylers": [{ "visibility": "on" }] }, { "featureType": "transit", "elementType": "labels", "stylers": [{ "hue": "#ff0000" }, { "saturation": "-100" }, { "visibility": "simplified" }] }, { "featureType": "water", "elementType": "all", "stylers": [{ "color": "#cbcbcb" }, { "visibility": "on" }] }, { "featureType": "water", "elementType": "geometry.fill", "stylers": [{ "color": "#f3f3f3" }, { "saturation": "0" }] }, { "featureType": "water", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "water", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }]

  };

  // Define your dark and light map styles
  var darkMapStyles = [
    // Dark map styles go here
    { "featureType": "all", "elementType": "labels", "stylers": [{ "visibility": "on" }] }, { "featureType": "all", "elementType": "labels.text.fill", "stylers": [{ "saturation": 36 }, { "color": "#1e242d" }, { "lightness": 40 }] }, { "featureType": "all", "elementType": "labels.text.stroke", "stylers": [{ "visibility": "on" }, { "color": "#1e242d" }, { "lightness": 0 }] }, { "featureType": "all", "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] }, { "featureType": "administrative", "elementType": "geometry.fill", "stylers": [{ "color": "#1e242d" }, { "lightness": 0 }] }, { "featureType": "administrative", "elementType": "geometry.stroke", "stylers": [{ "color": "#1e242d" }, { "lightness": 6 }, { "weight": 1.2 }] }, { "featureType": "administrative.country", "elementType": "labels.text.fill", "stylers": [{ "color": "#343e4d" }] }, { "featureType": "administrative.locality", "elementType": "labels.text.fill", "stylers": [{ "color": "#c4c4c4" }] }, { "featureType": "administrative.neighborhood", "elementType": "labels.text.fill", "stylers": [{ "color": "#343e4d" }] }, { "featureType": "landscape", "elementType": "geometry", "stylers": [{ "color": "#1e242d" }, { "lightness": 0 }] }, { "featureType": "poi", "elementType": "geometry", "stylers": [{ "color": "#1e242d" }, { "lightness": 9 }, { "visibility": "on" }] }, { "featureType": "poi.business", "elementType": "geometry", "stylers": [{ "visibility": "on" }] }, { "featureType": "road.highway", "elementType": "geometry.fill", "stylers": [{ "color": "#343e4d" }, { "lightness": "0" }] }, { "featureType": "road.highway", "elementType": "geometry.stroke", "stylers": [{ "visibility": "off" }] }, { "featureType": "road.highway", "elementType": "labels.text.fill", "stylers": [{ "color": "#ffffff" }] }, { "featureType": "road.highway", "elementType": "labels.text.stroke", "stylers": [{ "color": "#343e4d" }] }, { "featureType": "road.arterial", "elementType": "geometry", "stylers": [{ "color": "#1e242d" }, { "lightness": 8 }] }, { "featureType": "road.arterial", "elementType": "geometry.fill", "stylers": [{ "color": "#343e4d" }] }, { "featureType": "road.arterial", "elementType": "labels.text.fill", "stylers": [{ "color": "#ffffff" }] }, { "featureType": "road.arterial", "elementType": "labels.text.stroke", "stylers": [{ "color": "#2c2c2c" }] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [{ "color": "#1e242d" }, { "lightness": 0 }] }, { "featureType": "road.local", "elementType": "labels.text.fill", "stylers": [{ "color": "#999999" }] }, { "featureType": "transit", "elementType": "geometry", "stylers": [{ "color": "#1e242d" }, { "lightness": 7 }] }, { "featureType": "water", "elementType": "geometry", "stylers": [{ "color": "#1e242d" }, { "lightness": 6 }] }
  ];

  var lightMapStyles = [
    // Light map styles go here
    { "featureType": "administrative", "elementType": "geometry.fill", "stylers": [{ "visibility": "off" }] }, { "featureType": "administrative", "elementType": "labels.text", "stylers": [{ "visibility": "on" }, { "color": "#8e8e8e" }] }, { "featureType": "administrative", "elementType": "labels.text.fill", "stylers": [{ "color": "#7f7f7f" }] }, { "featureType": "administrative", "elementType": "labels.text.stroke", "stylers": [{ "visibility": "off" }] }, { "featureType": "administrative.country", "elementType": "geometry.stroke", "stylers": [{ "color": "#bebebe" }] }, { "featureType": "administrative.province", "elementType": "geometry.stroke", "stylers": [{ "visibility": "on" }, { "color": "#cbcbcb" }, { "weight": "0.69" }] }, { "featureType": "administrative.locality", "elementType": "geometry", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "landscape", "elementType": "all", "stylers": [{ "color": "#ffffff" }, { "saturation": "0" }] }, { "featureType": "poi.attraction", "elementType": "all", "stylers": [{ "visibility": "on" }, { "color": "#ffffff" }, { "saturation": "0" }] }, { "featureType": "poi.attraction", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.business", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.government", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.medical", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.park", "elementType": "geometry.fill", "stylers": [{ "color": "#efbfb7" }, { "visibility": "on" }] }, { "featureType": "poi.park", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.place_of_worship", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.school", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.sports_complex", "elementType": "all", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.sports_complex", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.sports_complex", "elementType": "labels.text", "stylers": [{ "visibility": "off" }] }, { "featureType": "road", "elementType": "all", "stylers": [{ "saturation": "-100" }, { "lightness": "50" }, { "gamma": "1" }] }, { "featureType": "road", "elementType": "geometry.fill", "stylers": [{ "color": "#e4e4e4" }, { "saturation": "0" }] }, { "featureType": "road.highway", "elementType": "all", "stylers": [{ "visibility": "simplified" }, { "saturation": "0" }] }, { "featureType": "road.highway", "elementType": "geometry", "stylers": [{ "color": "#f2f2f2" }, { "saturation": "0" }] }, { "featureType": "road.highway", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "road.highway", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "road.arterial", "elementType": "all", "stylers": [{ "saturation": "0" }] }, { "featureType": "road.arterial", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "road.arterial", "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] }, { "featureType": "road.local", "elementType": "all", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [{ "color": "#e4e4e4" }, { "lightness": "0" }, { "gamma": "1" }, { "saturation": "0" }] }, { "featureType": "road.local", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }, { "featureType": "transit", "elementType": "all", "stylers": [{ "visibility": "on" }] }, { "featureType": "transit", "elementType": "labels", "stylers": [{ "hue": "#ff0000" }, { "saturation": "-100" }, { "visibility": "simplified" }] }, { "featureType": "water", "elementType": "all", "stylers": [{ "color": "#cbcbcb" }, { "visibility": "on" }] }, { "featureType": "water", "elementType": "geometry.fill", "stylers": [{ "color": "#f3f3f3" }, { "saturation": "0" }] }, { "featureType": "water", "elementType": "labels", "stylers": [{ "visibility": "off" }] }, { "featureType": "water", "elementType": "labels.text", "stylers": [{ "visibility": "simplified" }] }
  ];

  // Check if the map style is stored in local storage
  var storedMapStyle = localStorage.getItem('mapStyle');
  var initialMapStyle = storedMapStyle === 'dark' ? darkMapStyles : lightMapStyles;

  var mapOptions = {
    // ... (Your existing map options)
    styles: initialMapStyle, // Use the stored map style or default to light
  };

  // Display a map on the web page
  var map = new google.maps.Map(document.getElementById("mapCanvasTwo"), mapOptions);
  map.setTilt(50);

  // Your existing code ...

  var darkMode = storedMapStyle === 'dark'; // Variable to track the map style

  // Add an event listener to the themeToggleBtn
  document.getElementById('themeToggleBtn').addEventListener('click', function () {
    // Toggle the darkMode variable
    darkMode = !darkMode;

    // Change the map style based on the darkMode variable
    map.setOptions({
      styles: darkMode ? darkMapStyles : lightMapStyles
    });

    // Store the current map style in local storage
    localStorage.setItem('mapStyle', darkMode ? 'dark' : 'light');

    // Toggle Bootstrap dark/light mode
    document.documentElement.setAttribute('data-bs-theme', darkMode ? 'dark' : 'light');
  });

  // Multiple markers location, latitude, and longitude
  var markers = [
    ['Hoboken, NJ, USA', 40.743992, -74.032364],
    ['Long Island City, Queens, NY, USA', 40.744678, -73.948540],
    ['Midwood, Brooklyn, NY, USA', 40.622581, -73.962799],
    ['Brooklyn Childrens Museum, museum, New York, United States', 40.67439655, -73.94404613465176],
    ['South Slope, neighbourhood, New York, United States', 40.6604004, -73.9944088],
    ['NewYork-Presbyterian Brooklyn Methodist Hospital, hospital, New York, United States', 40.667825449999995, -73.97914371327701]
  ];

  // Initialize Bootstrap 5 tooltips
  function initTooltips() {
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function (tooltip) {
      new bootstrap.Tooltip(tooltip);
    });
  }

  var imagePathsBase = "assets/images/place/";

  // Check the direction attribute of the HTML element
  var dirAttribute = document.documentElement.getAttribute("dir");

  if (dirAttribute === "rtl") {
    imagePathsBase = "../assets/images/place/";  // Update the base path for right-to-left
  }

  var imagePaths = [
    imagePathsBase + "/01.jpg",
    imagePathsBase + "/02.jpg",
    imagePathsBase + "/03.jpg",
    imagePathsBase + "/04.jpg",
    imagePathsBase + "/05.jpg",
    imagePathsBase + "/06.jpg",
  ];

  var cardTitle = [
    `Green Mart Apartment`,
    `Chuijhal Hotel And Restaurant`,
    `The Barber's Lounge`,
    `Gaming Expo Spectacle`,
    `Burnowl Tours & Travels `,
    `Exclusive Education Aid `,
  ];

  // Info window content
  var infoWindowContent = imagePaths.map(function (imagePath, index) {
    return [
      '<div class="card rounded-3 w-100 flex-fill overflow-hidden border-0 dark-overlay">' +
      '<a href="listings-map.html" class="stretched-link z-2"></a>' +
      '<div class="card-img-wrap card-image-hover overflow-hidden"><img src="' + imagePath + '" alt="" class="img-fluid"><div class="bg-primary card-badge d-inline-block text-white position-absolute z-1">10% OFF</div><div class="bg-primary card-badge d-inline-block text-white position-absolute z-1">$100 off $399: eblwc</div></div>' +
      '<div class="bottom-0 d-flex flex-column p-3 position-absolute position-relative text-white w-100 z-1"><div class="align-items-center card-start d-flex flex-wrap gap-1 mb-1"><i class="fa-solid fa-star"></i><span class="fw-medium"><span class="fs-6 fw-semibold me-1">(4.5)</span>2,391 reviews</span></div><h3 class="align-items-center d-flex flex-wrap fs-18 fw-medium gap-2 mb-0">' + cardTitle[index] + '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-patch-check-fill text-success" viewBox="0 0 16 16"><path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01-.622-.636zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708z"></path></svg></h3></div>' +
      '</div>'
    ];
  });

  //preparing the image so it can be used as a marker
  var iconBase = "assets/images/icon/";

  // Check the direction attribute of the HTML element
  var dirAttribute = document.documentElement.getAttribute("dir");

  if (dirAttribute === "rtl") {
    iconBase = "../assets/images/icon/";  // Update the base path for right-to-left
  }

  // Now you can use the updated iconBase variable

  var icons = {
    motorcycle: {
      icon: iconBase + "motorcycle-marker.png",
    },
    home: {
      icon: iconBase + "home-marker.png",
    },
    restaurant: {
      icon: iconBase + "restaurant-marker.png",
    },
    shopping: {
      icon: iconBase + "shopping-marker.png",
    },
    gymnasium: {
      icon: iconBase + "gymnasium-marker.png",
    },
    music: {
      icon: iconBase + "music-marker.png",
    },
  };

  var catIcon = [
    {
      position: new google.maps.LatLng(40.743992, -74.032364),
      type: "home",
    },
    {
      position: new google.maps.LatLng(40.744678, -73.948540),
      type: "motorcycle",
    },
    {
      position: new google.maps.LatLng(40.744678, -73.948540),
      type: "restaurant",
    },
    {
      position: new google.maps.LatLng(40.67439655, -73.94404613465176),
      type: "shopping",
    },
    {
      position: new google.maps.LatLng(40.6604004, -73.9944088),
      type: "gymnasium",
    },
    {
      position: new google.maps.LatLng(40.667825449999995, -73.97914371327701),
      type: "music",
    }
  ];

  // Add multiple markers to map
  var infoWindow = new google.maps.InfoWindow(), marker, i;

  // Place each marker on the map  
  for (i = 0; i < markers.length; i++) {
    var position = new google.maps.LatLng(markers[i][1], markers[i][2]);
    bounds.extend(position);
    marker = new google.maps.Marker({
      position: position,
      icon: icons[catIcon[i].type].icon,
      map: map,
      animation: google.maps.Animation.DROP,
      title: markers[i][0]
    });

    // Add hover animation
    marker.addListener('mouseover', function () {
      this.setAnimation(google.maps.Animation.BOUNCE);
    });

    marker.addListener('mouseout', function () {
      this.setAnimation(null);
    });

    // Add info window to marker    
    google.maps.event.addListener(marker, 'click', (function (marker, i) {
      return function () {
        infoWindow.setContent(infoWindowContent[i][0]);
        infoWindow.open(map, marker);
      }
    })(marker, i));

    // Center the map to fit all markers on the screen
    map.fitBounds(bounds);
  }

  // Set zoom level
  var boundsListener = google.maps.event.addListener((map), 'bounds_changed', function (event) {
    this.setZoom(12);
    google.maps.event.removeListener(boundsListener);
  });

}
// Load initialize function
google.maps.event.addDomListener(window, 'load', initMap);
