import { useEffect, useState } from 'react';
import './component/Banner/Banner.css';
import './component/Header/Header.css';
import './component/Form/Style.css';
import './component/Games/Games.css';
import Banner from './component/Banner';
import Form from './component/Form/Index';
import Nav from './component/Form/Nav';
import SectionHeading from './component/Form/SectionHeading';
import Results from './component/Games/Results';
import { fetchFixtures } from './services/api';

function formatDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function shiftDate(date, days) {
  const shifted = new Date(date);
  shifted.setDate(shifted.getDate() + days);
  return shifted;
}

function shiftDateString(value, days) {
  const [year, month, day] = value.split('-').map(Number);
  return formatDate(shiftDate(new Date(year, month - 1, day), days));
}

const today = new Date();
const initialFilters = {
  startDate: formatDate(today),
  endDate: formatDate(shiftDate(today, 7)),
  team: '',
  competition: '',
};

function App() {
  const [filters, setFilters] = useState(initialFilters);
  const [appliedFilters, setAppliedFilters] = useState(initialFilters);
  const [matches, setMatches] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [period, setPeriod] = useState('next');

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    fetchFixtures(appliedFilters, controller.signal)
      .then(setMatches)
      .catch((requestError) => {
        if (requestError.name !== 'AbortError') {
          setMatches([]);
          setError(requestError.message);
        }
      })
      .finally(() => setLoading(false));

    return () => controller.abort();
  }, [appliedFilters]);

  function handleFilterChange(event) {
    const { name, value } = event.target;
    setFilters((current) => ({ ...current, [name]: value }));
  }

  function handleSubmit(event) {
    event.preventDefault();
    setAppliedFilters(filters);
  }

  function applyPeriod(type) {
    const offset = type === 'previous' ? -7 : 7;
    const nextFilters = {
      ...filters,
      startDate: shiftDateString(filters.startDate, offset),
      endDate: shiftDateString(filters.endDate, offset),
    };

    setFilters(nextFilters);
    setAppliedFilters(nextFilters);
    setPeriod(type);
  }

  return (
    <>
      <main className="page" id="proximos">
        <Banner />

        <section className="content" aria-labelledby="page-title">
          <SectionHeading />
          <Nav period={period} onPrevious={() => applyPeriod('previous')} onNext={() => applyPeriod('next')} />
          <Form filters={filters} onFilterChange={handleFilterChange} onSubmit={handleSubmit} />
          {loading && <p className="empty-results" role="status">Carregando partidas...</p>}
          {!loading && error && (
            <div className="empty-results" role="alert">
              <p>{error}</p>
              <button type="button" onClick={() => setAppliedFilters({ ...appliedFilters })}>Tentar novamente</button>
            </div>
          )}
          {!loading && !error && <Results matches={matches} />}

          <nav className="bottom-nav" aria-label="Navegação entre períodos">
            <button className="button button-outline" type="button" onClick={() => applyPeriod('previous')}>← &nbsp;Jogos anteriores</button>
            <button className="button button-outline" type="button" onClick={() => applyPeriod('next')}>Próximos jogos&nbsp; →</button>
          </nav>
          <p className="notice">
            As informações de transmissão são exibidas quando publicadas. Confirme a programação com a emissora ou plataforma.
          </p>
        </section>
      </main>

      <footer className="site-footer">
        <div className="footer-inner">
          <a className="brand" href="#proximos" aria-label="Onde Passa o Jogo — início">
            <span className="brand-mark" aria-hidden="true">OP</span>
            <span>Onde Passa <strong>o Jogo</strong></span>
          </a>
          <p>Futebol na tela certa, sem perder o apito inicial.</p>
          <span>© 2026 Onde Passa o Jogo</span>
        </div>
      </footer>
    </>
  );
}

export default App;
