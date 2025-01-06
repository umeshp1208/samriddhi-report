$(document).ready(function () {
  $('#reportTable').DataTable({
    processing: true,
    serverSide: false,
    pageLength: 10,
    destroy: true,
    ajax: {
      url: 'ajax.php',
      type: 'GET',
      dataType: 'json',
      data: function (d) {
        d.f_state = $('#state').val()
        d.f_unit = $('#unit').val()
        d.f_date = $('#date').val()
        d.f_base_date = $('#base_date').val()
        d.growth = $('#growth').val();
        d.channel = $('#channel').val();
        return d;
      }
    },
    columns: [
      { data: 'state', orderable: true },
      { data: 'unit', orderable: true },
      { data: 'sap_code', orderable: true },
      { data: 'agency_name', orderable: true },
      { data: 'place', orderable: true },
      { data: 'base_nps', orderable: true },
      { data: 'today_nps', orderable: true },
      { data: 'growth_plus_minus', orderable: true },
      { data: 'growth_percentage', orderable: true },
      { data: 'first_barrier_percent', orderable: true },
      { data: 'excess_by_first_barrier', orderable: true },
      { data: 'second_barrier_percent', orderable: true },
      { data: 'excess_by_second_barrier', orderable: true }
    ],
    dom: '<"row"<"col-12"B>><"row"<"col-md-6"l><"col-md-6"f>>r<"col-12 mt-3 table-responsive"t><"row mt-3"<"col-md-6"i><"col-md-6"p>>',
    buttons: [
      {
        extend: 'excel',
        text: 'Export to Excel',
        className: 'btn btn-success btn-sm'
      }
    ],
    columnDefs: [
      { targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], className: 'text-center' }
    ]
  })
})
