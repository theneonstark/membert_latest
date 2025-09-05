/* ------------------------------------------------------------------------------
 *
 *  # Echarts - pies and donuts
 *
 *  Pies and donuts chart configurations
 *
 *  Version: 1.0
 *  Latest update: August 1, 2015
 *
 * ---------------------------------------------------------------------------- */

$(function () {

    // Set paths
    // ------------------------------

    require.config({
        paths: {
            echarts: 'assets/js/plugins/visualization/echarts'
        }
    });


    // Configuration
    // ------------------------------

    require(
        [
            'echarts',
            'echarts/theme/limitless',
            'echarts/chart/pie',
            'echarts/chart/funnel'
        ],


        // Charts setup
        function (ec, limitless) {


            // Initialize charts
            // ------------------------------
            var basic_donut = ec.init(document.getElementById('basic_donut'), limitless);

            // Charts setup
            // ------------------------------                    

            basic_donut_options = {

                // // Add title
                // title: {
                //     text: 'Browser popularity',
                //     subtext: 'Open source information',
                //     x: 'center'
                // },

                // // Add legend
                legend: {
                    orient: 'horizontal',
                    x: 'left',
                    data: ['Internet Explorer','Opera','Safari','Firefox','Chrome']
                },

                responsive :true,

                // // Display toolbox
                // toolbox: {
                //     show: true,
                //     orient: 'vertical',
                //     feature: {
                //         mark: {
                //             show: true,
                //             title: {
                //                 mark: 'Markline switch',
                //                 markUndo: 'Undo markline',
                //                 markClear: 'Clear markline'
                //             }
                //         },
                //         dataView: {
                //             show: true,
                //             readOnly: false,
                //             title: 'View data',
                //             lang: ['View chart data', 'Close', 'Update']
                //         },
                //         magicType: {
                //             show: true,
                //             title: {
                //                 pie: 'Switch to pies',
                //                 funnel: 'Switch to funnel',
                //             },
                //             type: ['pie', 'funnel'],
                //             option: {
                //                 funnel: {
                //                     x: '25%',
                //                     y: '20%',
                //                     width: '50%',
                //                     height: '70%',
                //                     funnelAlign: 'left',
                //                     max: 1548
                //                 }
                //             }
                //         },
                //         restore: {
                //             show: true,
                //             title: 'Restore'
                //         },
                //         saveAsImage: {
                //             show: true,
                //             title: 'Same as image',
                //             lang: ['Save']
                //         }
                //     }
                // },

                // Enable drag recalculate
                calculable: true,

                // Add series
                series: [
                    {
                        name: 'Browsers',
                        type: 'pie',
                        radius: ['50%', '70%'],
                        center: ['50%', '57.5%'],
                        itemStyle: {
                            normal: {
                                label: {
                                    show: false
                                },
                                labelLine: {
                                    show: false
                                }
                            },
                            emphasis: {
                                label: {
                                    show: true,
                                    formatter: '{b}' + '\n\n' + '{c} ({d}%)',
                                    position: 'center',
                                    textStyle: {
                                        fontSize: '17',
                                        fontWeight: '500'
                                    }
                                }
                            }
                        },

                        data: [
                            {value: 335, name: 'Internet Explorer'},
                            {value: 310, name: 'Opera'},
                            {value: 234, name: 'Safari'},
                            {value: 135, name: 'Firefox'},
                            {value: 1548, name: 'Chrome'}
                        ]
                    }
                ]
            };

            // Top text label
            var labelTop = {
                normal: {
                    label: {
                        show: true,
                        position: 'center',
                        formatter: '{b}\n',
                        textStyle: {
                            baseline: 'middle',
                            fontWeight: 300,
                            fontSize: 15
                        }
                    },
                    labelLine: {
                        show: false
                    }
                }
            };

            // Apply options
            // ------------------------------
            basic_donut.setOption(basic_donut_options);

            // Resize charts
            // ------------------------------

            window.onresize = function () {
                setTimeout(function (){
                    basic_donut.resize();
                }, 200);
            }
        }
    );
});
