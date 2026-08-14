package com.example

import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import com.example.ui.theme.CyanAccent
import com.example.ui.theme.PinkAccent
import com.example.ui.theme.PurpleAccent

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClassBottomSheet(
    activity: ActivityItem,
    viewModel: NovaViewModel,
    onDismissRequest: () -> Unit
) {
    val students by viewModel.students.collectAsState()
    var selectedTabIndex by remember { mutableIntStateOf(0) }
    val tabs = listOf("Asistencia", "Estructura", "Recursos")

    ModalBottomSheet(
        onDismissRequest = onDismissRequest,
        containerColor = MaterialTheme.colorScheme.surface,
        modifier = Modifier.fillMaxHeight(0.9f)
    ) {
        Column(modifier = Modifier.fillMaxSize()) {
            Text(
                text = activity.title,
                modifier = Modifier.padding(horizontal = 16.dp),
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            Spacer(modifier = Modifier.height(16.dp))
            TabRow(
                selectedTabIndex = selectedTabIndex,
                containerColor = Color.Transparent,
                contentColor = CyanAccent,
                indicator = { tabPositions ->
                    if (selectedTabIndex < tabPositions.size) {
                        TabRowDefaults.Indicator(
                            Modifier.tabIndicatorOffset(tabPositions[selectedTabIndex]),
                            color = CyanAccent
                        )
                    }
                }
            ) {
                tabs.forEachIndexed { index, title ->
                    Tab(
                        selected = selectedTabIndex == index,
                        onClick = { selectedTabIndex = index },
                        text = { Text(title, color = if (selectedTabIndex == index) CyanAccent else MaterialTheme.colorScheme.onSurfaceVariant) }
                    )
                }
            }
            Box(modifier = Modifier.weight(1f).padding(16.dp)) {
                when (selectedTabIndex) {
                    0 -> AttendanceTab(students, viewModel)
                    1 -> StructureTab()
                    2 -> DynamicsTab(students)
                }
            }
        }
    }
}

@Composable
fun AttendanceTab(students: List<Student>, viewModel: NovaViewModel) {
    val attendanceState = remember { mutableStateMapOf<String, Boolean>() }
    var showObservationMenuFor by remember { mutableStateOf<String?>(null) }
    val obsNotes = remember { mutableStateMapOf<String, String>() }

    LazyColumn {
        items(students) { student ->
            Row(
                modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.size(40.dp).clip(CircleShape).background(MaterialTheme.colorScheme.surfaceVariant), contentAlignment = Alignment.Center) {
                        Text(student.name.take(1).uppercase(), color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.Bold)
                    }
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(student.name, color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.Bold)
                        if (obsNotes.containsKey(student.id)) {
                            Text(obsNotes[student.id]!!, color = CyanAccent, fontSize = 12.sp)
                        }
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    IconButton(
                        onClick = { showObservationMenuFor = if (showObservationMenuFor == student.id) null else student.id },
                        modifier = Modifier.size(24.dp)
                    ) {
                        Icon(Icons.Default.ChatBubbleOutline, contentDescription = "Observation", tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(16.dp))
                    }
                }
                
                if (attendanceState.containsKey(student.id)) {
                    Text("Anotado", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
                } else {
                    Row {
                        IconButton(
                            onClick = { attendanceState[student.id] = true },
                            modifier = Modifier.background(Color(0xFF22C55E).copy(alpha=0.2f), CircleShape).size(36.dp)
                        ) {
                            Icon(Icons.Default.Check, contentDescription = "Asistió", tint = Color(0xFF22C55E))
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        IconButton(
                            onClick = { attendanceState[student.id] = false },
                            modifier = Modifier.background(Color(0xFFEF4444).copy(alpha=0.2f), CircleShape).size(36.dp)
                        ) {
                            Icon(Icons.Default.Close, contentDescription = "Faltó", tint = Color(0xFFEF4444))
                        }
                    }
                }
            }
            
            AnimatedVisibility(visible = showObservationMenuFor == student.id) {
                Row(modifier = Modifier.fillMaxWidth().padding(start = 52.dp, bottom = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("Participó", "Sin material", "Disruptivo").forEach { note ->
                        Box(modifier = Modifier.clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.surfaceVariant).clickable {
                            obsNotes[student.id] = note
                            showObservationMenuFor = null
                            viewModel.addAnecdote(student.id, note + " en clase viva.")
                        }.padding(horizontal = 8.dp, vertical = 4.dp)) {
                            Text(note, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun StructureTab() {
    Column {
        var timeRemaining by remember { mutableIntStateOf(0) }
        var isTimerRunning by remember { mutableStateOf(false) }
        var showAlert by remember { mutableStateOf(false) }

        LaunchedEffect(isTimerRunning, timeRemaining) {
            if (isTimerRunning && timeRemaining > 0) {
                delay(1000)
                timeRemaining--
                if (timeRemaining == 0) {
                    isTimerRunning = false
                    showAlert = true
                }
            }
        }

        Card(
            modifier = Modifier.fillMaxWidth().padding(bottom = 16.dp),
            colors = CardDefaults.cardColors(containerColor = if (showAlert) Color(0xFFEF4444).copy(alpha=0.3f) else MaterialTheme.colorScheme.surfaceVariant),
            shape = RoundedCornerShape(16.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp).fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Focus Timer", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
                val minutes = timeRemaining / 60
                val seconds = timeRemaining % 60
                val minStr = if (minutes < 10) "0$minutes" else "$minutes"
                val secStr = if (seconds < 10) "0$seconds" else "$seconds"
                Text("$minStr:$secStr", color = if (showAlert) Color.White else CyanAccent, fontSize = 36.sp, fontWeight = FontWeight.Bold)
                
                if (showAlert) {
                    Text("¡Tiempo terminado!", color = Color.White, fontSize = 14.sp)
                    Button(onClick = { showAlert = false }, colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.surface)) {
                        Text("Detener Alerta", color = MaterialTheme.colorScheme.onSurface)
                    }
                } else if (!isTimerRunning) {
                    Row(modifier = Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(onClick = { timeRemaining = 10 * 60; isTimerRunning = true }, colors = ButtonDefaults.buttonColors(containerColor = PurpleAccent)) { Text("10m", color = Color.White) }
                        Button(onClick = { timeRemaining = 15 * 60; isTimerRunning = true }, colors = ButtonDefaults.buttonColors(containerColor = PurpleAccent)) { Text("15m", color = Color.White) }
                        if (timeRemaining > 0) {
                            Button(onClick = { isTimerRunning = true }, colors = ButtonDefaults.buttonColors(containerColor = CyanAccent)) { Text("Reanudar", color=Color.Black) }
                        }
                    }
                } else {
                    OutlinedButton(onClick = { isTimerRunning = false }, modifier = Modifier.padding(top = 8.dp)) {
                        Text("Pausar", color = MaterialTheme.colorScheme.onSurface)
                    }
                }
            }
        }

        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            item { AccordionItem("Inicio (15m)", "Motivación, revisión de clase anterior, introducción al tema de hoy.") }
            item { AccordionItem("Desarrollo (45m)", "Exposición teórica, trabajo práctico en equipos, análisis de casos.") }
            item { AccordionItem("Cierre (15m)", "Conclusiones, retroalimentación grupal, dudas finales y ticket de salida.") }
        }
    }
}

@Composable
fun AccordionItem(title: String, content: String) {
    var expanded by remember { mutableStateOf(false) }
    Card(
        modifier = Modifier.fillMaxWidth().clickable { expanded = !expanded },
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha=0.5f))
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(title, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                Icon(if (expanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            AnimatedVisibility(visible = expanded) {
                Text(content, modifier = Modifier.padding(top = 8.dp), color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
            }
        }
    }
}

@Composable
fun DynamicsTab(students: List<Student>) {
    var selectedStudentName by remember { mutableStateOf("❓") }
    var isSpinning by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text("Dinámica de Grupo", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Spacer(modifier = Modifier.height(24.dp))
        
        Box(
            modifier = Modifier
                .size(200.dp)
                .clip(CircleShape)
                .background(Brush.radialGradient(listOf(PurpleAccent.copy(alpha=0.3f), Color.Transparent))),
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = selectedStudentName,
                fontSize = if (selectedStudentName == "❓") 64.sp else 28.sp,
                fontWeight = FontWeight.Bold,
                color = CyanAccent,
                textAlign = TextAlign.Center
            )
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Button(
            onClick = {
                if (students.isNotEmpty() && !isSpinning) {
                    isSpinning = true
                    scope.launch {
                        val spinCount = 20
                        val delayMs = 50L
                        for (i in 0..spinCount) {
                            selectedStudentName = students.random().name
                            delay(delayMs + (i * 5))
                        }
                        isSpinning = false
                    }
                }
            },
            colors = ButtonDefaults.buttonColors(containerColor = PinkAccent),
            shape = RoundedCornerShape(16.dp),
            modifier = Modifier.height(56.dp).fillMaxWidth(0.9f)
        ) {
            Icon(Icons.Default.AdsClick, contentDescription = null, tint = Color.White)
            Spacer(modifier = Modifier.width(8.dp))
            Text("Participación Aleatoria\n(Dedo Inteligente)", textAlign = TextAlign.Center, lineHeight = 16.sp, color = Color.White)
        }
    }
}
