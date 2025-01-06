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
        return d
      }
    },
    columns: [
      { data: 'state', orderable: false },
      { data: 'unit', orderable: false },
      { data: 'sap_code', orderable: false },
      { data: 'agency_name', orderable: false },
      { data: 'place', orderable: false },
      { data: 'base_nps', orderable: false },
      { data: 'today_nps', orderable: false },
      { data: 'growth_plus_minus', orderable: false },
      { data: 'growth_percentage', orderable: false },
      { data: 'first_barrier_percent', orderable: false },
      { data: 'excess_by_first_barrier', orderable: false },
      { data: 'second_barrier_percent', orderable: false },
      { data: 'excess_by_second_barrier', orderable: false }
    ],
    dom: '<"row"<"col-12"B>><"row"<"col-md-6"l><"col-md-6">>r<"col-12 mt-3 table-responsive"t><"row mt-3"<"col-md-6"i><"col-md-6"p>>',
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
