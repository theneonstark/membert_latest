/* ------------------------------------------------------------------------------
 *
 *  # Echarts - columns and waterfalls
 *
 *  Columns and waterfalls chart configurations
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
            'echarts/chart/bar',
            'echarts/chart/line'
        ],


        // Charts setup
        function (ec, limitless) {

            var stacked_columns = ec.init(document.getElementById('stacked_columns'), limitless);

            stacked_columns_options = {

                // Setup grid
                grid: {
                    x: 40,
                    x2: 47,
                    y: 35,
                    y2: 25
                },

                // Add tooltip
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow' // 'line' | 'shadow'
                    }
                },

                // Add legend
                legend: {
                    data: ['Aeps', 'Dmt', 'Recharge', 'Billpay', 'Pancard']
                },

                // Enable drag recalculate
                calculable: true,

                // Horizontal axis
                xAxis: [{
                    type: 'category',
                    data: ['Today', 'Month', 'Last Month']
                }],

                // Vertical axis
                yAxis: [{
                    type: 'value'
                }],

                // Add series
                series: [
                    {
                        name: 'Aeps',
                        type: 'bar',
                        data: [320, 332, 301, 334, 390, 330, 320]
                    },
                    {
                        name: 'Dmt',
                        type: 'bar',
                        data: [120, 132, 101, 134, 90, 230, 210]
                    },
                    {
                        name: 'Recharge',
                        type: 'bar',
                        data: [220, 182, 191, 234, 290, 330, 310]
                    },
                    {
                        name: 'Billpay',
                        type: 'bar',
                        data: [150, 232, 201, 154, 190, 330, 410]
                    },
                    {
                        name: 'Pancard',
                        type: 'bar',
                        data: [862, 1018, 964, 1026, 1679, 1600, 1570],
                        markLine: {
                            itemStyle: {
                                normal: {
                                    lineStyle: {
                                        type: 'dashed'
                                    }
                                }
                            },
                            data: [
                                [{type: 'min'}, {type: 'max'}]
                            ]
                        }
                    }
                ]
            };

            stacked_columns.setOption(stacked_columns_options);

            window.onresize = function () {
                setTimeout(function () {
                    stacked_columns.resize();
                }, 200);
            }
        }
    );
});
