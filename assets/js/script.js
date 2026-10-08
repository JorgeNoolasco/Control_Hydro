// Gráficos leves em SVG, sem bibliotecas ou dados inventados.
const dados = document.getElementById('dados-graficos');
if (dados) {
    const leituras = JSON.parse(dados.textContent);
    const ns = 'http://www.w3.org/2000/svg';
    function elemento(nome, atributos, texto) {
        const item = document.createElementNS(ns, nome);
        Object.entries(atributos).forEach(([chave, valor]) => item.setAttribute(chave, valor));
        if (texto !== undefined) item.textContent = texto;
        return item;
    }
    document.querySelectorAll('.grafico').forEach(container => {
        const valores = leituras.map(leitura => Number(leitura[container.dataset.campo]));
        const nivel = container.dataset.campo === 'nivel_reservatorio';
        const minimo = nivel ? 0 : Math.floor(Math.min(...valores) - 5);
        const maximo = nivel ? 100 : Math.ceil(Math.max(...valores) + 5);
        const svg = elemento('svg', { viewBox: '0 0 480 230', 'aria-hidden': 'true' });
        for (let i = 0; i <= 4; i++) {
            const y = 20 + i * 40;
            svg.append(elemento('line', { x1: 65, y1: y, x2: 460, y2: y }));
            svg.append(elemento('text', { x: 55, y: y + 4, 'text-anchor': 'end' },
                (maximo - i * (maximo - minimo) / 4).toLocaleString('pt-BR', { maximumFractionDigits: 1 })));
        }
        const pontos = valores.map((valor, i) => ({
            x: valores.length === 1 ? 262 : 65 + i * 395 / (valores.length - 1),
            y: 180 - (valor - minimo) / (maximo - minimo) * 160
        }));
        svg.append(elemento('polyline', { points: pontos.map(p => p.x + ',' + p.y).join(' ') }));
        pontos.forEach((p, i) => {
            const ponto = elemento('circle', { cx: p.x, cy: p.y, r: 4 });
            ponto.append(elemento('title', {}, leituras[i].data_registro + ': ' +
                valores[i].toLocaleString('pt-BR') + ' ' + container.dataset.unidade));
            svg.append(ponto);
        });
        const rotulo = leitura => {
            const [data, hora] = leitura.data_registro.split(' ');
            return data.slice(8, 10) + '/' + data.slice(5, 7) + ' ' + hora.slice(0, 5);
        };
        svg.append(elemento('text', { x: 65, y: 212 }, rotulo(leituras[0])));
        svg.append(elemento('text', { x: 460, y: 212, 'text-anchor': 'end' }, rotulo(leituras[leituras.length - 1])));
        container.append(svg);
    });
}

// O navegador valida os campos antes do evento submit.
document.querySelector('[data-leitura]')?.addEventListener('submit', event => {
    const botao = event.currentTarget.querySelector('button[type="submit"]');
    botao.disabled = true;
    botao.textContent = 'Salvando…';
});
window.addEventListener('pageshow', () => {
    const botao = document.querySelector('[data-leitura] button[type="submit"]');
    if (botao) { botao.disabled = false; botao.textContent = 'Salvar leitura'; }
});
