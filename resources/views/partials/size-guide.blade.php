<div class="modal fade" id="sizeGuide" tabindex="-1" aria-labelledby="sizeGuideTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="sizeGuideTitle">Guía de tallas</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <p class="text-body-secondary small">
                    Medidas de la prenda en plano, en centímetros. El pecho se mide
                    de costura a costura y se multiplica por dos.
                </p>

                <table class="table size-guide__table align-middle">
                    <thead>
                        <tr><th>Talla</th><th>Pecho</th><th>Largo</th><th>Manga</th></tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['S', '96', '68', '20'],
                            ['M', '104', '71', '21'],
                            ['L', '112', '74', '22'],
                            ['XL', '120', '77', '23'],
                            ['XXL', '128', '80', '24'],
                        ] as [$size, $chest, $length, $sleeve])
                            <tr>
                                <td class="fw-bold">{{ $size }}</td>
                                <td>{{ $chest }} cm</td>
                                <td>{{ $length }} cm</td>
                                <td>{{ $sleeve }} cm</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="alert alert-info py-2 small mb-0">
                    <strong>Compara siempre las medidas.</strong> El ajuste puede variar
                    entre fabricantes y temporadas. Si dudas entre dos tallas, utiliza
                    pecho y largo como referencia en lugar de fijarte solo en la etiqueta.
                </div>
            </div>
        </div>
    </div>
</div>
