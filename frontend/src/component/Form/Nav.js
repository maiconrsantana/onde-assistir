function Nav() {
  return (
        <nav className="period-nav" aria-label="Período das partidas">
          <a href="#anteriores">← Jogos anteriores</a>
          <a className="active" href="#proximos">Próximos jogos →</a>
        </nav>
  );
}

export default Nav;

