import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X, FileDown, DollarSign, FileText } from 'lucide-react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

const KardexModal = ({ isOpen, onClose, credito }) => {
  const [kardexData, setKardexData] = useState([]);
  const [cuotasReales, setCuotasReales] = useState([]);
  const [formData, setFormData] = useState({
    monto: '',
    interesMensual: '',
    meses: 12,
    tipoPago: 'Mensual',
  });

  const redondearMonedaPeruana = (valor) => {
    const centimos = Math.round((valor - Math.floor(valor)) * 100);
    let nuevoCentimo = 0;
    
    if (centimos < 10) {
      nuevoCentimo = 0;
    } else if (centimos < 35) {
      nuevoCentimo = 20;
    } else if (centimos < 75) {
      nuevoCentimo = 50;
    } else {
      return Math.ceil(valor);
    }
    
    return Math.floor(valor) + (nuevoCentimo / 100);
  };

  // Pre-cargar valores desde el crédito y obtener cuotas reales
  useEffect(() => {
    if (!isOpen || !credito) return;
    
    const monto = Number(credito.monto_aprobado ?? credito.monto_original ?? 0);
    const meses = Number(credito.plazos_meses ?? 12);
    const interesMensual = Number(credito.tasa_interes ?? 0);
    const tipoPago = credito.periodo_pago || 'Mensual';
    
    console.log('Datos del crédito para Kardex:', {
      id_credito: credito.id_credito,
      periodo_pago: credito.periodo_pago,
      tipoPago,
      monto,
      meses,
      interesMensual
    });
    
    setFormData({ monto, interesMensual, meses, tipoPago });
    
    // Obtener cuotas reales de la base de datos usando el servicio de API
    const fetchCuotas = async () => {
      try {
        // Importar el servicio dinámicamente con la ruta correcta
        const { creditsService } = await import('../../services/api');
        
        console.log('[API] Obteniendo crédito con cuotas, ID:', credito.id_credito);
        
        const response = await creditsService.getById(credito.id_credito);
        console.log('[DATA] Respuesta completa del backend:', response);
        
        if (response.success && response.data?.cuotas) {
          console.log('[OK] Cuotas encontradas:', response.data.cuotas.length);
          console.log('Primera cuota de BD:', response.data.cuotas[0]);
          setCuotasReales(response.data.cuotas);
        } else {
          console.warn('[WARN] No se encontraron cuotas en la respuesta');
        }
      } catch (error) {
        console.error('[ERROR] Error al obtener cuotas:', error);
      }
    };
    
    fetchCuotas();
  }, [isOpen, credito]);

  useEffect(() => {
    if (!isOpen) return;
    // Autogenerar cuando hay datos Y cuotas reales cargadas
    if (formData.monto && formData.interesMensual !== '' && formData.meses) {
      // Esperar un momento para que las cuotas se carguen
      const timer = setTimeout(() => {
        generarKardex();
      }, 500);
      return () => clearTimeout(timer);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [formData.monto, formData.interesMensual, formData.meses, formData.tipoPago, isOpen, cuotasReales]);

  const calcularFechas = (tipoPago, numPagos) => {
    const fechas = [];
    let fechaActual = new Date();
    let delta = 1;

    // Determinar el incremento según el tipo de pago
    if (tipoPago === 'Diario') {
      delta = 1;
    } else if (tipoPago === 'Semanal') {
      delta = 7;
    } else if (tipoPago === 'Quincenal') {
      delta = 15;
    } else if (tipoPago === 'Mensual') {
      delta = 30;
    }

    // IMPORTANTE: El primer pago debe ser después del período correspondiente
    // Avanzar la fecha inicial según el tipo de pago
    fechaActual.setDate(fechaActual.getDate() + delta);

    // Generar fechas hasta completar el número de pagos
    while (fechas.length < numPagos) {
      // Saltar domingos (día 0) - si cae domingo, mover al lunes
      if (fechaActual.getDay() === 0) {
        fechaActual.setDate(fechaActual.getDate() + 1);
      }
      
      fechas.push(new Date(fechaActual));
      
      // Avanzar a la siguiente fecha
      fechaActual = new Date(fechaActual);
      fechaActual.setDate(fechaActual.getDate() + delta);
    }

    return fechas;
  };

  const generarKardex = () => {
    try {
      const monto = parseFloat(formData.monto);
      const interesMensual = parseFloat(formData.interesMensual) / 100;
      const meses = parseInt(formData.meses, 10);

      if (isNaN(monto) || isNaN(interesMensual) || isNaN(meses)) {
        alert('Por favor ingrese valores válidos');
        return;
      }

      // Si hay cuotas reales de la base de datos, usar los montos pero recalcular las fechas correctamente
      if (cuotasReales && cuotasReales.length > 0) {
        console.log('[OK] Usando cuotas reales de la base de datos con fechas recalculadas:', cuotasReales.length, 'cuotas');
        console.log('Primera cuota original:', cuotasReales[0]);
        
        // Recalcular las fechas correctamente
        const fechasCorrectas = calcularFechas(formData.tipoPago, cuotasReales.length);
        
        const kardex = cuotasReales.map((cuota, index) => {
          const fechaCorrecta = fechasCorrectas[index];
          const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
          const diaSemana = diasSemana[fechaCorrecta.getDay()];
          
          // Calcular saldo restante
          const cuotasRestantes = cuotasReales.slice(index + 1);
          const saldo = cuotasRestantes.reduce((sum, c) => sum + parseFloat(c.monto_total || 0), 0);
          
          return {
            nroCuota: cuota.numero_cuota,
            diaSemana: diaSemana,
            fechaPago: fechaCorrecta, // Usar la fecha recalculada
            interes: parseFloat(cuota.monto_interes || 0),
            capital: parseFloat(cuota.monto_capital || 0),
            cuota: parseFloat(cuota.monto_total || 0),
            saldo: saldo,
            estado: cuota.estado
          };
        });
        
        console.log('Primera cuota con fecha corregida:', kardex[0]);
        setKardexData(kardex);
        return;
      }

      // Fallback: generar fechas si no hay cuotas en la BD (no debería pasar)
      console.warn('No hay cuotas reales, generando fechas calculadas');
      const montoTotal = monto * (1 + interesMensual * meses);
      const tipoPago = formData.tipoPago;
      
      let numPagos = meses;
      if (tipoPago === 'Diario') {
        numPagos = Math.ceil(meses * 26);
      } else if (tipoPago === 'Semanal') {
        numPagos = Math.ceil(meses * 4);
      } else if (tipoPago === 'Quincenal') {
        numPagos = meses * 2;
      }

      const fechas = calcularFechas(tipoPago, numPagos);
      const cuota = redondearMonedaPeruana(montoTotal / numPagos);
      
      const kardex = [];
      let saldo = montoTotal;
      
      for (let i = 0; i < numPagos; i++) {
        const fecha = fechas[i] || new Date();
        const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const diaSemana = diasSemana[fecha.getDay()];
        
        const interes = (monto * interesMensual) / numPagos;
        const capital = cuota - interes;
        saldo = Math.max(0, saldo - cuota);
        
        kardex.push({
          nroCuota: i + 1,
          diaSemana: diaSemana,
          fechaPago: fecha,
          interes: interes,
          capital: capital,
          cuota: cuota,
          saldo: saldo
        });
      }

      setKardexData(kardex);
    } catch (error) {
      console.error('Error al generar kardex:', error);
      alert('Error al generar el kardex. Por favor verifique los datos.');
    }
  };

  const descargarPDFDirecto = () => {
    // Crear PDF con encabezado, tabla simplificada y pie de página
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('portrait'); // Orientación vertical
    
    const cliente = `${credito?.nombres || ''} ${credito?.apellido_paterno || ''} ${credito?.apellido_materno || ''}`.trim();
    
    // ========== ENCABEZADO ==========
    // Rectángulo azul de encabezado
    doc.setFillColor(37, 99, 235);
    doc.rect(0, 0, 210, 35, 'F');
    
    // Nombre de la empresa
    doc.setFontSize(11);
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'normal');
    doc.text('Computadoras Mayta', 105, 12, { align: 'center' });
    
    // Título principal
    doc.setFontSize(20);
    doc.setFont('helvetica', 'bold');
    doc.text('KARDEX DE PAGO', 105, 22, { align: 'center' });
    
    // Subtítulo
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Cronograma Detallado de Cuotas', 105, 29, { align: 'center' });
    
    // ========== INFORMACIÓN DEL CRÉDITO ==========
    // Recuadro de información más alto para acomodar mejor el contenido
    doc.setFillColor(250, 250, 250);
    doc.roundedRect(10, 38, 190, 16, 1, 1, 'F');
    doc.setDrawColor(200, 200, 200);
    doc.setLineWidth(0.3);
    doc.roundedRect(10, 38, 190, 16, 1, 1, 'S');
    
    doc.setFontSize(8);
    doc.setTextColor(50, 50, 50);
    const infoY = 42;
    
    // Primera línea - mejor distribuida
    doc.setFont('helvetica', 'bold');
    doc.text('ID Crédito:', 12, infoY);
    doc.setFont('helvetica', 'normal');
    doc.text(String(credito?.id_credito || '-'), 30, infoY);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Cliente:', 45, infoY);
    doc.setFont('helvetica', 'normal');
    const clienteText = (cliente || '-').substring(0, 25); // Limitar longitud
    doc.text(clienteText, 58, infoY);
    
    doc.setFont('helvetica', 'bold');
    doc.text('DNI:', 120, infoY);
    doc.setFont('helvetica', 'normal');
    doc.text(String(credito?.dni || '-'), 130, infoY);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Fecha:', 155, infoY);
    doc.setFont('helvetica', 'normal');
    doc.text(new Date().toLocaleDateString('es-PE'), 168, infoY);
    
    // Segunda línea - mejor espaciado
    const infoY2 = 47;
    doc.setFont('helvetica', 'bold');
    doc.text('Monto:', 12, infoY2);
    doc.setFont('helvetica', 'normal');
    doc.text(`S/. ${(parseFloat(formData.monto)||0).toFixed(2)}`, 24, infoY2);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Tasa:', 50, infoY2);
    doc.setFont('helvetica', 'normal');
    doc.text(`${(parseFloat(formData.interesMensual)||0).toFixed(1)}%`, 60, infoY2);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Plazo:', 80, infoY2);
    doc.setFont('helvetica', 'normal');
    doc.text(`${formData.meses} ${formData.meses === 1 ? 'mes' : 'meses'}`, 92, infoY2);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Frecuencia:', 125, infoY2);
    doc.setFont('helvetica', 'normal');
    doc.text(formData.tipoPago, 145, infoY2);
    
    doc.setFont('helvetica', 'bold');
    doc.text('N° Cuotas:', 170, infoY2);
    doc.setFont('helvetica', 'normal');
    doc.text(`${kardexData.length}`, 185, infoY2);
    
    // Tercera línea para información adicional si es necesario
    const infoY3 = 52;
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(7);
    doc.setTextColor(100, 100, 100);
    doc.text('Kardex generado automáticamente - Computadoras Mayta', 105, infoY3, { align: 'center' });
    
    // ========== TABLA DE CUOTAS ==========
    // Tabla de datos simplificada - solo 5 columnas
    const tableData = kardexData.map(item => [
      String(item.nroCuota),
      format(item.fechaPago, 'dd/MM/yyyy', { locale: es }),
      `S/ ${item.cuota.toFixed(2)}`,
      '', // Mora - vacío
      ''  // Firma - vacío
    ]);
    
    // Calcular ancho de tabla para centrarla
    const tableWidth = 170; // Ancho fijo de la tabla
    const pageWidth = 210;
    const leftMargin = (pageWidth - tableWidth) / 2;
    
    doc.autoTable({
      startY: 58,
      head: [['CUOTA', 'FECHAS', 'MONTO', 'MORA', 'FIRMA']],
      body: tableData,
      theme: 'grid',
      headStyles: {
        fillColor: [245, 245, 245],
        textColor: [0, 0, 0],
        fontStyle: 'bold',
        fontSize: 10,
        halign: 'center',
        lineWidth: 0.5,
        lineColor: [150, 150, 150],
        cellPadding: 4
      },
      bodyStyles: {
        fontSize: 10,
        textColor: [0, 0, 0],
        lineWidth: 0.3,
        lineColor: [200, 200, 200],
        halign: 'center',
        fillColor: [255, 255, 255]
      },
      alternateRowStyles: {
        fillColor: [255, 255, 255]
      },
      columnStyles: {
        0: { cellWidth: 30, halign: 'center' },  // CUOTA - más ancho
        1: { cellWidth: 42, halign: 'center' },  // FECHAS
        2: { cellWidth: 32, halign: 'center' },  // MONTO
        3: { cellWidth: 32, halign: 'center' },  // MORA
        4: { cellWidth: 34, halign: 'center' }   // FIRMA
      },
      styles: {
        cellPadding: 4,
        lineColor: [200, 200, 200],
        lineWidth: 0.3,
        fontSize: 10,
        overflow: 'linebreak'
      },
      margin: { left: leftMargin, right: leftMargin },
      tableWidth: tableWidth
    });
    
    // ========== PIE DE PÁGINA ==========
    const finalY = doc.lastAutoTable.finalY + 15;
    
    // Recuadro de agradecimiento
    doc.setFillColor(245, 247, 250);
    doc.roundedRect(30, finalY, 150, 18, 3, 3, 'F');
    doc.setDrawColor(37, 99, 235);
    doc.setLineWidth(0.5);
    doc.roundedRect(30, finalY, 150, 18, 3, 3, 'S');
    
    // Texto de agradecimiento
    doc.setFontSize(11);
    doc.setTextColor(37, 99, 235);
    doc.setFont('helvetica', 'bold');
    doc.text('GRACIAS POR SU PREFERENCIA', 105, finalY + 7, { align: 'center' });
    
    doc.setFontSize(8);
    doc.setTextColor(80, 80, 80);
    doc.setFont('helvetica', 'normal');
    doc.text('Estamos comprometidos con su confianza y crecimiento financiero', 105, finalY + 12, { align: 'center' });
    
    doc.setFontSize(7);
    doc.setTextColor(100, 100, 100);
    doc.setFont('helvetica', 'italic');
    doc.text('Computadoras Mayta', 105, finalY + 16, { align: 'center' });
    
    // Guardar PDF
    doc.save(`Kardex_Credito_${credito?.id_credito || 'N/A'}_${new Date().getTime()}.pdf`);
  };

  const exportarPDF = () => {
    // Formato simplificado: solo tabla con n° cuota, fecha, monto, mora y firma
    const html = `
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Kardex de Pago - Crédito ${credito?.id_credito || ''}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { 
      font-family: Arial, sans-serif; 
      padding: 30px; 
      background: white;
      color: #000;
      font-size: 12pt;
    }
    .container {
      max-width: 210mm;
      margin: 0 auto;
      background: white;
    }
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin: 20px 0;
      font-size: 11pt;
    }
    th { 
      border-bottom: 1px solid #000;
      padding: 10px 8px;
      font-weight: bold;
      text-align: left;
    }
    td { 
      border-bottom: 1px solid #ccc; 
      padding: 8px; 
      text-align: left;
    }
    tr:last-child td {
      border-bottom: none;
    }
    @media print {
      body { background: white; padding: 20px; }
      @page {
        margin: 15mm;
        size: A4;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Tabla de Cuotas -->
    <table>
      <thead>
        <tr>
          <th>CUOTA</th>
          <th>FECHAS</th>
          <th>MONTO</th>
          <th>MORA</th>
          <th>FIRMA</th>
        </tr>
      </thead>
      <tbody>
        ${kardexData.map(item => {
          const fechaStr = format(new Date(item.fechaPago), 'dd/MM/yyyy', { locale: es });
          return `
          <tr>
            <td>${item.nroCuota}</td>
            <td>${fechaStr}</td>
            <td>S/ ${item.cuota.toFixed(2)}</td>
            <td></td>
            <td></td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>
  </div>
  <script>window.onload = () => { window.print(); };</script>
 </body>
 </html>`;
    const w = window.open('', '_blank');
    if (!w) return alert('Bloqueado por el navegador. Habilite ventanas emergentes para descargar.');
    w.document.open();
    w.document.write(html);
    w.document.close();
  };

  const exportarExcel = () => {
    // Crear datos para Excel
    const cliente = `${credito?.nombres || ''} ${credito?.apellido_paterno || ''} ${credito?.apellido_materno || ''}`;
    const totalInteres = kardexData.reduce((sum, item) => sum + item.interes, 0);
    const totalCapital = kardexData.reduce((sum, item) => sum + item.capital, 0);
    const totalCuotas = kardexData.reduce((sum, item) => sum + item.cuota, 0);
    const totalPagar = (parseFloat(formData.monto) || 0) + totalInteres;

    // Crear contenido CSV (compatible con Excel)
    let csv = '\uFEFF'; // BOM para UTF-8
    csv += 'Computadoras Mayta\n';
    csv += 'Kardex de Pago - Cronograma Detallado de Cuotas\n\n';
    
    // Información del crédito
    csv += `ID Crédito:,${credito?.id_credito || '-'}\n`;
    csv += `Cliente:,${cliente}\n`;
    csv += `DNI:,${credito?.dni || '-'}\n`;
    csv += `Monto:,S/. ${(parseFloat(formData.monto)||0).toFixed(2)}\n`;
    csv += `Tasa Mensual:,${(parseFloat(formData.interesMensual)||0).toFixed(2)}%\n`;
    csv += `Plazo:,${formData.meses} meses\n`;
    csv += `Frecuencia:,${formData.tipoPago}\n`;
    csv += `N° de Cuotas:,${kardexData.length} cuotas\n`;
    csv += `Fecha Emisión:,${new Date().toLocaleDateString('es-PE')}\n\n`;
    
    // Encabezados de tabla
    csv += 'N° Cuota,Día de Semana,Fecha de Pago,Interés (S/.),Capital (S/.),Cuota (S/.),Saldo (S/.),Firma\n';
    
    // Datos de cuotas
    kardexData.forEach(item => {
      csv += `${item.nroCuota},`;
      csv += `${item.diaSemana},`;
      csv += `${format(item.fechaPago, 'dd/MM/yyyy', { locale: es })},`;
      csv += `${item.interes.toFixed(2)},`;
      csv += `${item.capital.toFixed(2)},`;
      csv += `${item.cuota.toFixed(2)},`;
      csv += `${item.saldo.toFixed(2)},`;
      csv += `\n`;
    });
    
    // Fila de totales
    csv += `,,TOTAL,${totalInteres.toFixed(2)},${totalCapital.toFixed(2)},${totalCuotas.toFixed(2)},,\n\n`;
    csv += `,,Total a Pagar:,S/. ${totalPagar.toFixed(2)}\n`;
    
    // Crear blob y descargar
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `Kardex_Credito_${credito?.id_credito || 'N/A'}_${new Date().getTime()}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  if (!isOpen) return null;

  // Estilos para la barra de scroll
  const scrollbarStyles = `
    .kardex-scroll::-webkit-scrollbar {
      width: 12px;
    }
    .kardex-scroll::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 6px;
    }
    .kardex-scroll::-webkit-scrollbar-thumb {
      background: #cbd5e0;
      border-radius: 6px;
    }
    .kardex-scroll::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
  `;

  return createPortal(
    <>
      <style>{scrollbarStyles}</style>
      <div className="modal-overlay">
        <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ 
        maxWidth: '95vw', 
        maxHeight: '95vh', 
        width: '1200px',
        height: '90vh',
        margin: '20px auto',
        padding: '0',
        borderRadius: '12px',
        overflow: 'hidden',
        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
        display: 'flex',
        flexDirection: 'column'
      }}>
        <div className="bg-white w-full h-full flex flex-col" style={{
          '--scrollbar-width': '8px',
          '--scrollbar-track': '#f1f5f9',
          '--scrollbar-thumb': '#cbd5e0',
          '--scrollbar-thumb-hover': '#94a3b8'
        }}>
        {/* Header del Modal */}
        <div className="flex items-center justify-between bg-gradient-to-r from-blue-600 to-blue-700 border-b shadow-lg" style={{ margin: '20px', padding: '20px', borderRadius: '12px 12px 0 0' }}>
          <div className="flex items-center space-x-6">
            <div className="bg-white/20 p-3 rounded-xl shadow-md">
              <FileText className="text-white" size={28} />
            </div>
            <div className="space-y-1">
              <div className="text-blue-100 text-xs font-semibold uppercase tracking-wider">Computadoras Mayta</div>
              <h2 className="text-white text-2xl font-bold tracking-tight">Kardex de Pago</h2>
              <div className="text-blue-200 text-sm font-medium">
                Crédito #{credito?.id_credito ?? '-'} • {[credito?.nombres, credito?.apellido_paterno, credito?.apellido_materno].filter(Boolean).join(' ') || 'Cliente'}
              </div>
            </div>
          </div>
          <button 
            onClick={onClose}
            className="text-white/80 hover:text-white hover:bg-white/20 p-3 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg"
            title="Cerrar"
          >
            <X size={26} />
          </button>
        </div>
        
        {/* Contenido del Modal */}
        <div className="flex-1 bg-white kardex-scroll" style={{ 
          height: 'calc(90vh - 180px)',
          overflowY: 'scroll',
          overflowX: 'hidden',
          margin: '0 20px',
          border: '1px solid #e5e7eb',
          borderRadius: '0 0 12px 12px'
        }}>
          <div style={{ padding: '20px' }}>
            {/* Header del documento */}
            <div className="text-center mb-6">
              <h2 className="text-xl font-bold text-gray-800 mb-4">Cronograma de Pagos</h2>
            </div>
            
            {/* Información del Cliente y Crédito en formato simple */}
            <div className="mb-6">
              <div className="grid grid-cols-2 gap-8 mb-4">
                <div>
                  <div className="mb-2">
                    <span className="font-semibold">Cliente:</span>{`${credito?.nombres || ''} ${credito?.apellido_paterno || ''} ${credito?.apellido_materno || ''}`.trim().toUpperCase()}
                  </div>
                  <div className="mb-2">
                    <span className="font-semibold">Dirección:</span>{credito?.direccion || 'no me acuerdo'}
                  </div>
                </div>
                <div className="text-right">
                  <div className="mb-2">
                    <span className="font-semibold">Teléfono:</span>{credito?.telefono || '963542424'}
                  </div>
                  <div className="mb-2">
                    <span className="font-semibold">DNI:</span>{credito?.dni || '22222222'}
                  </div>
                </div>
              </div>
              
              {/* Información del crédito en formato horizontal */}
              <div className="flex justify-between items-center bg-gray-50 p-4 rounded-lg mb-4">
                <div className="flex items-center space-x-2">
                  <span className="font-semibold text-gray-600">Préstamo:</span>
                  <span className="text-lg font-bold text-green-600">S/ {(parseFloat(formData.monto) || 0).toFixed(2)}</span>
                </div>
                <div className="flex items-center space-x-2">
                  <span className="font-semibold text-gray-600">Cuota:</span>
                  <span className="text-lg font-bold text-blue-600">S/ {kardexData.length > 0 ? kardexData[0].cuota.toFixed(2) : '0.00'}</span>
                </div>
                <div className="flex items-center space-x-2">
                  <span className="font-semibold text-gray-600">Tasa:</span>
                  <span className="text-lg font-bold text-gray-800">{(parseFloat(formData.interesMensual) || 0).toFixed(1)}%</span>
                </div>
                <div className="flex items-center space-x-2">
                  <span className="font-semibold text-gray-600">Crédito:</span>
                  <span className="text-lg font-bold text-gray-800">#{credito?.id_credito || 'N/A'}</span>
                </div>
              </div>
            </div>

            {kardexData.length === 0 && (
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                <div className="text-gray-400 mb-4">
                  <FileText size={64} className="mx-auto" />
                </div>
                <p className="text-gray-600 text-lg font-medium mb-2">
                  Generando Kardex...
                </p>
                <p className="text-gray-500 text-sm">
                  Cargando cronograma de pagos del crédito
                </p>
              </div>
            )}

            {kardexData.length > 0 && (
              <div>
                {/* Header de la sección de cronograma */}
                <div className="flex justify-between items-center mb-4">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-800">Cronograma de Pagos</h3>
                    <p className="text-sm text-gray-600">
                      {kardexData.length} cuotas {formData.tipoPago.toLowerCase()}s • {formData.meses} {formData.meses === 1 ? 'mes' : 'meses'} de plazo
                    </p>
                  </div>
                  <button
                    onClick={descargarPDFDirecto}
                    className="flex items-center px-6 py-3 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105 font-semibold text-sm border-0"
                    title="Exportar cronograma a PDF"
                  >
                    <FileDown size={18} className="mr-3" /> 
                    Exportar PDF
                  </button>
                </div>
                
                {/* Nota informativa simple */}
                <div className="mb-4">
                  <p className="text-sm text-gray-600">
                    <strong>Nota:</strong> Los domingos no son días operativos.
                  </p>
                </div>
                
                {/* Tabla simple sin bordes complejos */}
                <div className="overflow-x-auto">
                  <table className="min-w-full bg-white text-sm border-collapse">
                    <thead>
                      <tr className="border-b-2 border-gray-300">
                        <th className="py-2 px-4 text-left font-semibold text-gray-700">Cuota</th>
                        <th className="py-2 px-4 text-left font-semibold text-gray-700">Fecha</th>
                        <th className="py-2 px-4 text-left font-semibold text-gray-700">Monto</th>
                        <th className="py-2 px-4 text-left font-semibold text-gray-700">Mora</th>
                        <th className="py-2 px-4 text-left font-semibold text-gray-700">Firma</th>
                      </tr>
                    </thead>
                    <tbody>
                      {kardexData.map((item, index) => {
                        const esNoOperativo = new Date(item.fechaPago).getDay() === 0;
                        return (
                        <tr key={index} className="border-b border-gray-200">
                          <td className="py-2 px-4 text-gray-800">
                            {item.nroCuota}
                          </td>
                          <td className="py-2 px-4 text-gray-800">
                            {format(item.fechaPago, 'dd/MM/yyyy', { locale: es })}
                          </td>
                          <td className="py-2 px-4 text-green-600 font-medium">
                            S/ {item.cuota.toFixed(2)}
                          </td>
                          <td className="py-2 px-4 text-gray-400">
                            —
                          </td>
                          <td className="py-2 px-4 text-gray-400">
                            —
                          </td>
                        </tr>
                      )})}
                    </tbody>
                  </table>
                </div>
              
              {/* Pie de Página */}
              <div className="mt-4 border-2 border-black p-4 bg-white">
                <p className="mb-2"><strong>RESPONSABLE DE COBRANZA:</strong> Moises Mayta M.</p>
                <p className="mb-2"><strong>Telf. 964945187 YAPE</strong></p>
                <div className="bg-yellow-100 border border-yellow-400 p-3 mb-2">
                  <p className="font-bold text-sm">
                    La puntualidad en sus pagos es la mejor garantia para sus proximos créditos, evite el pago de mora de: S/ 1.00 x día atrazado.
                  </p>
                </div>
                <p><strong>OFICINA PRINCIPAL:</strong> JR. FRANCISCO IRAZOLA NRO. 421 - SATIPO</p>
              </div>


            </div>
          )}
          </div>
        </div>
        
        {/* Footer del Modal */}
        <div className="bg-white border-t border-gray-200 flex justify-center items-center" style={{ margin: '0 20px 20px 20px', padding: '20px', borderRadius: '0 0 12px 12px' }}>
          <div className="text-sm text-gray-500 font-medium">
            Kardex generado el {new Date().toLocaleDateString('es-PE')} • Computadoras Mayta
          </div>
        </div>
      </div>
        </div>
      </div>
    </>,
    document.body
  );
};

export default KardexModal;
