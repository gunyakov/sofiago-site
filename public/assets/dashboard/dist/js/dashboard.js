(function ($) {
    "use strict"; // Start of use strict
    /*
        - Counter
        - Data table
        - Popup youtube & gallery
        - File Up
        - Apex Charts
        - Tooltip
    */
    /*-------------------------------------------
        Counter
    --------------------------------------------- */
    if ($('.counter').length) {
        $('.counter').counterUp({
            delay: 1,
            time: 500,
        });
    }
    /*-------------------------------------------
        Data table
    --------------------------------------------- */
    if ($('.category-list').length) {
        $('.category-list').DataTable({
            language: {
                oPaginate: {
                    sNext: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
                    sPrevious: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>'
                }
            }
        });
    }
    /*-------------------------------------------
        Popup youtube & gallery
    --------------------------------------------- */
    if ($('.zoom-gallery').length) {
        $('.zoom-gallery').magnificPopup({
            delegate: 'a',
            type: 'image',
            closeOnContentClick: false,
            closeBtnInside: false,
            mainClass: 'mfp-with-zoom mfp-img-mobile',
            image: {
                verticalFit: true,
                titleSrc: function (item) {
                    return item.el.attr('title') + ' &middot; <a class="image-source-link" href="' + item.el.attr('data-source') + '" target="_blank">image source</a>';
                }
            },
            gallery: {
                enabled: true
            },
            zoom: {
                enabled: true,
                duration: 300, // don't foget to change the duration also in CSS
                opener: function (element) {
                    return element.find('img');
                }
            }
        });
    }

    /*-------------------------------------------
        File Up
    --------------------------------------------- */
    if ($('.fileUp').length) {
        $('.fileUp').FancyFileUpload({
            params: {
                action: 'fileuploader'
            },
            maxfilesize: 1000000
        });
    }

    /*-------------------------------------------
        Apex Charts
    --------------------------------------------- */

    if ($('#chart').length) {
        // Dark Mode Setup
        const darkMode = localStorage.getItem('dark-mode') || 'light';
        $('html').toggleClass('dark', darkMode === 'dark');
        $('.dark-button').toggle(darkMode !== 'dark');
        $('.light-button').toggle(darkMode === 'dark');

        // ApexCharts Options
        var lightOptions = {
            // Light theme options
            colors: ['#f84525'],
            series: [
                {
                    data: [10, 20, 15, 30, 35, 30, 45, 59, 30, 35, 25, 29, 15]
                }
            ],
            chart: {
                type: "area",
                height: 350,
                zoom: {
                    enabled: false
                },
                toolbar: {
                    tools: {
                        download: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-grid" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/></svg>'
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                colors: ["#FFFFFF"]
            },
            stroke: {
                curve: "smooth",
                width: 3,
            },
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    type: "vertical",
                    colorStops: [
                        [
                            {
                                offset: 0,
                                color: "#f84525",
                                opacity: 1.0
                            },
                            {
                                offset: 70,
                                color: "#f7b733",
                                opacity: 0.2
                            },
                            {
                                offset: 97,
                                color: "#f7b733",
                                opacity: 0.0
                            }
                        ]
                    ]
                }
            },
            xaxis: {
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: "#aaa"
                    }
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            grid: {
                borderColor: "#eff2f7"
            },
            legend: {
                horizontalAlign: "left"
            }
        };

        var darkOptions = {
            colors: ['#f84525'],
            series: [
                {
                    data: [10, 20, 15, 30, 35, 30, 45, 59, 30, 35, 25, 29, 15]
                }
            ],
            chart: {
                type: "area",
                height: 350,
                zoom: {
                    enabled: false
                },
                toolbar: {
                    tools: {
                        download: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-grid" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/></svg>'
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                colors: ["#FFFFFF"]
            },
            stroke: {
                curve: "smooth",
                width: 3,
            },
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    type: "vertical",
                    colorStops: [
                        [
                            {
                                offset: 0,
                                color: "#f84525",
                                opacity: 1.0
                            },
                            {
                                offset: 70,
                                color: "#f7b733",
                                opacity: 0.2
                            },
                            {
                                offset: 97,
                                color: "#f7b733",
                                opacity: 0.0
                            }
                        ]
                    ]
                }
            },
            xaxis: {
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: "#aaa"
                    }
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            grid: {
                borderColor: "#26292d"
            },
            legend: {
                horizontalAlign: "left"
            },
        };

        var options = darkMode === 'dark' ? darkOptions : lightOptions;

        var chart = new ApexCharts(document.querySelector("#chart"), options);
        chart.render();


        // Toggle dark UI
        $('.dark-button, .light-button').on('click', function () {
            const isDark = $(this).hasClass('dark-button');
            $('.dark-button').toggle(!isDark);
            $('.light-button').toggle(isDark);
            $('html').toggleClass('dark', isDark);
            localStorage.setItem('dark-mode', isDark ? 'dark' : 'light');

            // Update chart theme
            options = isDark ? darkOptions : lightOptions;
            chart.updateOptions(options);

        });
    }

    /*-------------------------------------------
        Tooltip
    --------------------------------------------- */
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))


})(jQuery);

